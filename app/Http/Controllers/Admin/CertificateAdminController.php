<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendCertificateEmailJob;
use App\Models\Certificate;
use App\Models\CertificateEndorsement;
use App\Models\Recipient;
use App\Models\Setting;
use App\Models\User;
use App\Services\CertificateGenerationService;
use App\Services\EndorsementGenerationCoordinator;
use App\Services\EndorsementTelegramNotifier;
use App\Services\RecipientMatchingService;
use Carbon\Carbon;
use App\Support\RegionalDirectorSignatory;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateAdminController extends Controller
{
    // Aliased rather than duplicated: CertificateGenerationService owns these
    // now, and both the preview endpoints here and the queued generation job
    // must stamp at identical coordinates.
    private const STANDARD_NAME_POS_X = CertificateGenerationService::STANDARD_NAME_POS_X;
    private const STANDARD_NAME_POS_Y = CertificateGenerationService::STANDARD_NAME_POS_Y;
    private const STANDARD_NAME_FONT_SIZE = CertificateGenerationService::STANDARD_NAME_FONT_SIZE;
    private const STANDARD_NAME_FONT_FAMILY = CertificateGenerationService::STANDARD_NAME_FONT_FAMILY;
    private const NOT_APPLICABLE = CertificateGenerationService::NOT_APPLICABLE;
    private const CUSTOM_DOST_PROJECT_OPTION = 'Others';
    private const PARTICIPANTS_FILE_MAX_KB = 2048;
    private const CERTIFICATE_TEMPLATE_MAX_KB = 51200;

    public function __construct(
        private readonly CertificateGenerationService $generator,
        private readonly EndorsementTelegramNotifier $telegram,
        private readonly EndorsementGenerationCoordinator $generation,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $isRegionalDirector = $this->isRegionalDirector($user);
        $canEndorseCertificates = $this->canEndorseCertificates($user);
        $canDownloadCertificates = $this->canDownloadCertificates($user);
        $canViewAnalytics = $this->canViewAnalytics($user);

        $query = Certificate::query();
        $search = trim((string) $request->get('q', ''));
        $group = (string) $request->get('group', '');

        if ($search !== '') {
            $needle = mb_strtolower($search);
                $query->where(function ($builder) use ($needle) {
                $builder
                    ->whereRaw('LOWER(certificate_code) like ?', ['%' . $needle . '%'])
                    ->orWhereRaw('LOWER(participant_name) like ?', ['%' . $needle . '%'])
                    ->orWhereRaw('LOWER(training_title) like ?', ['%' . $needle . '%'])
                    ->orWhereRaw('LOWER(issuing_office) like ?', ['%' . $needle . '%'])
                    ->orWhereRaw('LOWER(status) like ?', ['%' . $needle . '%']);
            });
        }

        $endorsementBaseQuery = CertificateEndorsement::query();
        if (!$isRegionalDirector && $user) {
            $endorsementBaseQuery->where('submitted_by', $user->id);
        }
        $pendingEndorsementsCount = (clone $endorsementBaseQuery)
            ->whereIn('status', [
                CertificateEndorsement::STATUS_ENDORSED,
                CertificateEndorsement::STATUS_GENERATING,
            ])
            ->count();
        if ($group === 'training') {
            $groups = $query
                ->select(
                    'training_title',
                    'training_date',
                    'training_date_to',
                    'issuing_office',
                    DB::raw('MIN(created_at) as created_at'),
                    DB::raw('COUNT(*) as total_count'),
                    DB::raw("SUM(CASE WHEN stamped_pdf_path IS NOT NULL AND stamped_pdf_path != '' THEN 1 ELSE 0 END) as pdf_count")
                )
                ->groupBy('training_title', 'training_date', 'training_date_to', 'issuing_office')
                ->orderByDesc('created_at')
                ->orderByDesc('training_date')
                ->orderBy('training_title')
                ->paginate(10)
                ->withQueryString();

            return view('admin.certificates.index', compact(
                'groups',
                'search',
                'group',
                'isRegionalDirector',
                'canEndorseCertificates',
                'canDownloadCertificates',
                'canViewAnalytics',
                'pendingEndorsementsCount'
            ));
        }

        if ($group === 'endorsements') {
            $endorsementsQuery = CertificateEndorsement::query()
                ->orderByDesc('created_at');

            if (!$isRegionalDirector && $user) {
                $endorsementsQuery->where('submitted_by', $user->id);
            }

            $endorsements = $endorsementsQuery->paginate(10)->withQueryString();

            $endorsements->each(function (CertificateEndorsement $endorsement): void {
                $payload = is_array($endorsement->payload) ? $endorsement->payload : [];
                $endorsement->setAttribute('training_title', $payload['training_title'] ?? 'Untitled');
                $endorsement->setAttribute('issuing_office', $payload['issuing_office'] ?? '');
                $endorsement->setAttribute('date_range', $this->formatEndorsementDateRange($payload));
            });

            return view('admin.certificates.index', compact(
                'endorsements',
                'search',
                'group',
                'isRegionalDirector',
                'canEndorseCertificates',
                'canDownloadCertificates',
                'canViewAnalytics',
                'pendingEndorsementsCount'
            ));
        }

        $certs = $query->orderByDesc('id')->paginate(7)->withQueryString();
        return view('admin.certificates.index', compact(
            'certs',
            'search',
            'group',
            'isRegionalDirector',
            'canEndorseCertificates',
            'canDownloadCertificates',
            'canViewAnalytics',
            'pendingEndorsementsCount'
        ));
    }

    public function approvals(Request $request)
    {
        $user = $request->user();
        $this->ensureRegionalDirectorAction($user);

        $pendingEndorsements = CertificateEndorsement::query()
            ->with(['submitter:id,name'])
            ->whereIn('status', [
                CertificateEndorsement::STATUS_ENDORSED,
                CertificateEndorsement::STATUS_GENERATING,
            ])
            ->orderByDesc('created_at')
            ->paginate(5, ['*'], 'queue_page')
            ->withQueryString();

        $pendingEndorsements->each(function (CertificateEndorsement $endorsement) use ($request): void {
            $payload = is_array($endorsement->payload) ? $endorsement->payload : [];
            $endorsement->setAttribute('date_range', $this->formatEndorsementDateRange($payload));
            $endorsement->setAttribute('participants_preview', []);
            $endorsement->setAttribute('participants_remaining_count', 0);
            $endorsement->setAttribute('participants_review_ready', false);
            $endorsement->setAttribute('participants_preview_error', null);
            $endorsement->setAttribute('first_participant_name', null);
            $endorsement->setAttribute('generation_progress', $this->generation->progress($endorsement));

            if (empty($endorsement->participants_file_path)) {
                return;
            }

            try {
                $participants = $this->parseParticipantStoragePath((string) $endorsement->participants_file_path);
                $names = array_values(array_filter(array_map(
                    fn (array $participant) => trim((string) ($participant['name'] ?? '')),
                    $participants
                )));

                $endorsement->setAttribute('participants_preview', array_slice($names, 0, 8));
                $endorsement->setAttribute('participants_remaining_count', max(0, count($names) - 8));
                $endorsement->setAttribute('first_participant_name', $names[0] ?? null);

                if (!empty($names) && ! $endorsement->isGenerating()) {
                    $request->session()->put($this->endorsementParticipantsReviewedSessionKey($endorsement->id), true);
                    $endorsement->setAttribute('participants_review_ready', true);
                }
            } catch (\Throwable $e) {
                $endorsement->setAttribute('participants_preview_error', 'Unable to preview participants on phone.');
            }
        });

        $today = now('Asia/Manila')->toDateString();
        $approvedToday = CertificateEndorsement::query()
            ->where('status', CertificateEndorsement::STATUS_RD_APPROVED)
            ->whereDate('rd_approved_at', $today)
            ->count();
        $rejectedToday = CertificateEndorsement::query()
            ->where('status', CertificateEndorsement::STATUS_RD_REJECTED)
            ->whereDate('rd_rejected_at', $today)
            ->count();

        $recentDecisions = CertificateEndorsement::query()
            ->with(['submitter:id,name'])
            ->whereIn('status', [
                CertificateEndorsement::STATUS_RD_APPROVED,
                CertificateEndorsement::STATUS_RD_REJECTED,
            ])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        $recentDecisions->each(function (CertificateEndorsement $endorsement): void {
            $payload = is_array($endorsement->payload) ? $endorsement->payload : [];
            $endorsement->setAttribute('date_range', $this->formatEndorsementDateRange($payload));
        });

        return view('admin.certificates.approvals', [
            'pendingEndorsements' => $pendingEndorsements,
            'pendingCount' => $pendingEndorsements->total(),
            'approvedToday' => $approvedToday,
            'rejectedToday' => $rejectedToday,
            'recentDecisions' => $recentDecisions,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        if (!$this->canPrepareCertificate($user)) {
            abort(403, 'You are not allowed to prepare certificate endorsement requests.');
        }

        $officeCodeMap = [
            'DOST Caraga - Fields Operation Division' => 'FOD',
            'DOST Caraga - Financial and Administrative Services' => 'FAS',
            'DOST Caraga - Office of the Regional Director' => 'ORD',
            'DOST Caraga - Technical Support Services' => 'TSS',
            'DOST Caraga - Innovation Unit' => 'IU',
            'DOST Caraga - PSTO-Agusan Del Norte' => 'ADN',
            'DOST Caraga - PSTO-Agusan Del Sur' => 'ADS',
            'DOST Caraga - PSTO-Surigao Del Norte' => 'SDN',
            'DOST Caraga - PSTO-Surigao Del Sur' => 'SDS',
            'DOST Caraga - PSTO-Province of Dinagat Island' => 'PDI',
        ];
        $defaultOffice = array_key_first($officeCodeMap);
        // defaults (you can change these)
        $defaults = [
            'issuing_office' => $defaultOffice,
        ];
        $topics = $this->topics();
        $activityTypes = $this->activityTypes();
        $certificateTypes = $this->certificateTypes();
        $certificateTemplateFiles = $this->defaultTemplateFileByCertificateType();
        $automaticCertificateTypeByRecipientType = $this->automaticCertificateTypeByRecipientType();
        $recipientTypes = $this->recipientTypes();
        $dostPrograms = $this->dostPrograms();
        $dostProjects = $this->dostProjects();
        $sourceOfFundsOptions = $this->sourceOfFundsOptions();
        $automaticSourceOfFundsByProgram = $this->automaticSourceOfFundsByProgram();
        $nationalRegularProgramLabel = $this->nationalRegularProgramLabel();
        $dostProgramProjectPrefixes = $this->dostProgramProjectPrefixes();
        $setupProgramLabel = $this->setupProgramLabel();
        $setupOfficeProvinces = $this->setupOfficeProvinces();
        $sscpProgramLabel = $this->sscpProgramLabel();
        $customDostProjectOptionLabel = $this->customDostProjectOptionLabel();
        $pillars = $this->pillars();
        $isRegionalDirector = $this->isRegionalDirector($user);

        return view('admin.certificates.create', compact(
            'defaults',
            'topics',
            'activityTypes',
            'certificateTypes',
            'certificateTemplateFiles',
            'automaticCertificateTypeByRecipientType',
            'recipientTypes',
            'dostPrograms',
            'dostProjects',
            'sourceOfFundsOptions',
            'automaticSourceOfFundsByProgram',
            'nationalRegularProgramLabel',
            'dostProgramProjectPrefixes',
            'setupProgramLabel',
            'setupOfficeProvinces',
            'sscpProgramLabel',
            'customDostProjectOptionLabel',
            'pillars',
            'isRegionalDirector'
        ));
    }

    private function topics(): array
    {
        return [
            'Food',
            'Metals and Engineering',
            'Textile',
            'Startups and Technopreneurship',
            'Circular Economy and Sustainable Innovations',
            'Emerging Technologies',
            'Others',
        ];
    }

    private function activityTypes(): array
    {
        return [
            'Training',
            'Workshop',
            'Seminar',
            'Webinar',
            'Conference',
            'Others',
        ];
    }

    private function certificateTypes(): array
    {
        return [
            'Certificate of Appreciation',
            'Certificate of Participation',
            'Certificate of Recognition',
            'Certificate of Commendation',
            'Certificate of Completion',
        ];
    }

    private function defaultTemplateFileByCertificateType(): array
    {
        return [
            'Certificate of Appreciation' => 'Appreciation.pdf',
            'Certificate of Participation' => 'Participation.pdf',
            'Certificate of Recognition' => 'Recognition.pdf',
            'Certificate of Commendation' => 'Commendation.pdf',
            'Certificate of Completion' => 'Completion.pdf',
        ];
    }

    private function defaultTemplatePathForCertificateType(string $certificateType): string
    {
        $defaultFileName = $this->defaultTemplateFileByCertificateType()[$certificateType] ?? null;
        if ($defaultFileName === null) {
            throw new \RuntimeException('No embedded template is configured for the selected certificate type.');
        }

        $absolutePath = public_path('templates/' . $defaultFileName);
        if (!is_file($absolutePath)) {
            throw new \RuntimeException("Default template file not found: {$defaultFileName}. Please ensure it exists in public/templates/ or upload a custom template.");
        }

        return $absolutePath;
    }

    private function storeTemplatePdfForRequest(array $data, Request $request, string $directory): string
    {
        if (($data['template_source'] ?? null) === 'custom' && $request->hasFile('certificate_pdf_shared')) {
            return $request->file('certificate_pdf_shared')->storeAs(
                $directory,
                'template_' . Str::uuid() . '.pdf',
                'local'
            );
        }

        $sourceAbs = $this->defaultTemplatePathForCertificateType((string) ($data['certificate_type'] ?? ''));
        $localPath = trim($directory, '/') . '/template_' . Str::uuid() . '.pdf';
        Storage::disk('local')->put($localPath, file_get_contents($sourceAbs));

        return $localPath;
    }

    private function automaticCertificateTypeByRecipientType(): array
    {
        return [
            'Participant' => 'Certificate of Participation',
            'Resource Person/Speaker' => 'Certificate of Appreciation',
        ];
    }

    private function recipientTypes(): array
    {
        return [
            'Participant',
            'Resource Person/Speaker',
            'Facilitator',
            'Trainer',
            'Panelist',
            'Organizer',
            'Technical Support',
            'Guest of Honor',
            'Evaluator/Judge',
            'Others',
        ];
    }

    private function dostPrograms(): array
    {
        return $this->generator->dostPrograms();
    }

    private function nationalRegularProgramLabel(): string
    {
        return CertificateGenerationService::PROGRAM_NATIONAL_REGULAR;
    }

    private function sourceOfFundsOptions(): array
    {
        return [
            $this->regularFundsLabel(),
            $this->projectFundsLabel(),
            'Trust Funds',
            'N/A',
        ];
    }

    private function regularFundsLabel(): string
    {
        return 'Regular Funds';
    }

    private function projectFundsLabel(): string
    {
        return 'Project Funds';
    }

    private function automaticSourceOfFundsByProgram(): array
    {
        return [
            $this->nationalRegularProgramLabel() => $this->regularFundsLabel(),
            'LGIA (Local Grants-in-Aid Program)' => $this->projectFundsLabel(),
            'CEST (Community Empowerment through Science and Technology Program)' => $this->projectFundsLabel(),
            $this->sscpProgramLabel() => $this->projectFundsLabel(),
        ];
    }

    private function sscpProgramLabel(): string
    {
        return CertificateGenerationService::PROGRAM_SSCP;
    }

    private function customDostProjectOptionLabel(): string
    {
        return 'Others, please specify';
    }

    private function setupProgramLabel(): string
    {
        return CertificateGenerationService::PROGRAM_SETUP;
    }

    private function setupOfficeProvinces(): array
    {
        return [
            'Regional Office (Main)',
            'PSTO-Agusan Del Norte',
            'PSTO-Agusan Del Sur',
            'PSTO-Surigao Del Norte',
            'PSTO-Surigao Del Sur',
            'PSTO-Province of Dinagat Island',
        ];
    }

    private function dostProgramProjectPrefixes(): array
    {
        return [
            'LGIA (Local Grants-in-Aid Program)' => 'LGIA',
            'CEST (Community Empowerment through Science and Technology Program)' => 'CEST',
            $this->sscpProgramLabel() => 'SSCP',
        ];
    }

    private function dostProjects(): array
    {
        $projects = [
            ['name' => self::NOT_APPLICABLE, 'code' => self::NOT_APPLICABLE],
            ['name' => 'InnoMines: Innovating the Mining Industry through the Establishment of Mulberry-based Mine Rehabilitation Technology and Supporting Mineral Innovation in Agusan del Norte', 'code' => 'LGIA-2026-01'],
            ['name' => 'Mobilizing Actions for Greater Hazard Awareness, Preparedness, and Disaster Adaptation (MAG-HANDA)', 'code' => 'LGIA-2026-02'],
            ['name' => 'HIMO: Hub for Innovation and Manufacturing Operations through the Enhanced Makerspaces and the Advanced Manufacturing in Caraga (AMCen)', 'code' => 'LGIA-2026-03'],
            ['name' => 'Accelerating the Development of Caraga thru the Establishment of Smart and Sustainable Communities (ACCESS)', 'code' => 'LGIA-2026-04'],
            ['name' => 'Food Assurance and Safety through Science and Technology in the Caraga Region (FASST Caraga)', 'code' => 'LGIA-2026-05'],
            ['name' => 'Operationalizing the RRDIC to Lead Innovation Policy Reforms and Systems Thinking', 'code' => 'LGIA-2026-06'],
            ['name' => 'Pathways for Research, Opportunities, Product Enhancement and Leveraging through the Caraga Food Innovation Consortium (PROPEL-CFIC)', 'code' => 'LGIA-2026-07'],
            ['name' => 'ELEV8 RISE Caraga: Ecosystem Leadership and Emerging Ventures in AI & DeepTech for Regional Innovation and Startup Empowerment', 'code' => 'LGIA-2026-08'],
            ['name' => 'Driving Grassroots Innovation toward Inclusive, Sustainable, Circular Regional Development (GI-DRIVE)', 'code' => 'LGIA-2026-09'],
            ['name' => 'CIRCULATES Caraga: Community-Integrated Circular Economy Solutions through STI4CE-Driven Innovation', 'code' => 'LGIA-2026-10'],
            ['name' => 'CT Support for the Operationalization and Development of the Network of Open Virtual AI (NOVA) Hub and Regional AI Ecosystem for Startups and Workforce Development', 'code' => 'LGIA-2026-11'],
            ['name' => "OneDOST4U: Promoting DOST's Science, Technology, and Innovation Initiatives through Strategic Communication and the National and Regional S&T Week Celebrations", 'code' => 'LGIA-2026-12'],
            ['name' => 'TechConnect: Facilitating Technology Transfer, Adoption, and Upgrading - A Project Supporting the Operations for Fairness Opinion Board (FOB) and SETUP', 'code' => 'LGIA-2026-13'],
            ['name' => 'Techy Business Para sa Makabagong Bayani: Creating OFW Technopreneurs through Nationwide Implementation of the iFWD PH Program', 'code' => 'LGIA-2026-14'],
            ['name' => 'SMARTER AGUSAN: Strengthening CEST Communities through Advancement and Leveraging of Existing Processes via Technology Systems Upgrading (SCALEUP)', 'code' => 'CEST-2026-01'],
            ['name' => 'Project FARM-RISE: Forestry, Agriculture, and Renewable Materials for Rehabilitation, Innovation, Sustainability and Energy.', 'code' => 'CEST-2026-02'],
            ['name' => 'ACTION PDI: Advancing Circular Transformation and Innovation for Modern Agriculture Towards a Progressive Dinagat Islands', 'code' => 'CEST-2026-03'],
            ['name' => 'Generating Rural Advantage through Innovative Dairy Industry Development (Project GRANDE)', 'code' => 'CEST-2026-04'],
            ['name' => 'Project FARM: A Project Facilitating Agricultural Resilience and Modernization in the Smart Community of Surigao del Sur', 'code' => 'CEST-2026-05'],
            ['name' => 'Reinvigorating the Seaweed (Kappaphycus spp.) Industry in Surigao del Sur through STI-based Interventions for a Sustainable Blue Economy', 'code' => 'CEST-2026-06'],
            ['name' => 'SMARTER AGUSAN: Deploying a Rural Model for Electric Mobility and Charging Infrastructure in Buenavista, Agusan del Norte (E-Move)', 'code' => 'SSCP-2026-01'],
            ['name' => 'SMARTForward ADS 2026: Strategic Modernization through Adaptive and Resilient Technologies for Smart and Sustainable Communities in Agusan del Sur', 'code' => 'SSCP-2026-02'],
            ['name' => 'Promoting Resilient Opportunities for Growth through Smart and Sustainable Communities in the Municipality of San Jose (PROGRESS-San Jose)', 'code' => 'SSCP-2026-03'],
            ['name' => 'Strengthening Smart and Sustainable Governance in PLGU-Surigao del Norte and LGU-Mainit through Digital Transformation and Strategic Roadmapping', 'code' => 'SSCP-2026-04'],
            ['name' => 'Building a Smart and Sustainable City through STI-based Technologies for Tandag City (SMART Tandag: Year 2)', 'code' => 'SSCP-2026-05'],
        ];

        return array_map(function (array $project): array {
            $project['program_prefix'] = $this->dostProjectProgramPrefix((string) ($project['code'] ?? ''));

            return $project;
        }, $projects);
    }

    private function dostProjectProgramPrefix(string $projectCode): string
    {
        if ($projectCode === '' || $projectCode === self::NOT_APPLICABLE || !str_contains($projectCode, '-')) {
            return '';
        }

        return Str::before($projectCode, '-');
    }

    private function hasDostProjectsForPrefix(string $programPrefix): bool
    {
        foreach ($this->dostProjects() as $project) {
            if (($project['program_prefix'] ?? '') === $programPrefix) {
                return true;
            }
        }

        return false;
    }

    private function isDostProjectAllowedForProgram(string $program, string $projectName): bool
    {
        $requiredPrefix = $this->dostProgramProjectPrefixes()[$program] ?? '';
        if ($requiredPrefix === '' || !$this->hasDostProjectsForPrefix($requiredPrefix)) {
            return true;
        }

        $projectCode = (string) ($this->dostProjectCodeMap()[$projectName] ?? '');

        return $this->dostProjectProgramPrefix($projectCode) === $requiredPrefix;
    }

    private function isSetupProgram(string $program): bool
    {
        return $program === $this->setupProgramLabel();
    }

    private function isNationalRegularProgram(string $program): bool
    {
        return $program === $this->nationalRegularProgramLabel();
    }

    private function isProjectFundProgram(string $program): bool
    {
        return in_array($program, array_keys($this->automaticSourceOfFundsByProgram()), true)
            && !$this->isNationalRegularProgram($program);
    }

    private function isSscpProgram(string $program): bool
    {
        return $program === $this->sscpProgramLabel();
    }

    private function dostProjectCodeMap(): array
    {
        $map = [];
        foreach ($this->dostProjects() as $project) {
            $name = (string) ($project['name'] ?? '');
            $code = (string) ($project['code'] ?? '');
            if ($name !== '' && $code !== '') {
                $map[$name] = $code;
            }
        }

        return $map;
    }

    private function pillars(): array
    {
        return [
            'Human Well-Being Promoted',
            'Wealth Creation Fostered',
            'Wealth Protection Reinforced',
            'Sustainability Institutionalized',
            'Not Applicable',
        ];
    }

    public function store(Request $request)
    {
        $this->ensureRegionalDirectorAction($request->user());

        [$data, $participants] = $this->validatedCertificatePayload($request);

        $sharedPath = $this->storeTemplatePdfForRequest($data, $request, 'certificates/source');

        try {
            $generatedCertificates = $this->generateCertificatesFromPayload(
                $this->buildTrainingPayload($data),
                $participants,
                $sharedPath,
                true
            );
        } catch (\Throwable $e) {
            return back()->withErrors([$e->getMessage()])->withInput();
        } finally {
            Storage::disk('local')->delete($sharedPath);
        }

        $generated = count($generatedCertificates);

        return redirect()
            ->route('admin.certs.index')
            ->with('success', "Regional Director generated {$generated} certificates with QR and signatory block.");
    }

    public function endorse(Request $request)
    {
        $user = $request->user();
        if (!$this->canEndorseCertificates($user)) {
            abort(403, 'Only organizer/supervising unit roles can endorse certificate requests.');
        }

        [$data, $participants] = $this->validatedCertificatePayload($request);

        $matchingService = app(RecipientMatchingService::class);
        $matchResults = [];
        $unresolvedCount = 0;

        foreach ($participants as $i => $participant) {
            $result = $matchingService->match($participant);
            $matchResults[$i] = $result;
            if ($result['recipient_id'] === null) {
                $unresolvedCount++;
            }
        }

        if ($unresolvedCount > 0) {
            $participantsFile = $request->file('participants_file');
            $participantsExt = strtolower((string) $participantsFile->getClientOriginalExtension());
            $participantsFilePath = $participantsFile->storeAs(
                'certificate-endorsements/participants',
                'participants_' . Str::uuid() . '.' . $participantsExt,
                'local'
            );

            $templatePdfPath = $this->storeTemplatePdfForRequest($data, $request, 'certificate-endorsements/templates');

            $sessionData = $data;
            unset($sessionData['participants_file'], $sessionData['certificate_pdf_shared']);

            $request->session()->put('pending_match_review', [
                'participants' => $participants,
                'results' => $matchResults,
                'data' => $sessionData,
                'participants_file_path' => $participantsFilePath,
                'template_pdf_path' => $templatePdfPath,
            ]);

            return redirect()->route('admin.certs.matching-review')
                ->with('warning', $unresolvedCount . ' participant(s) could not be auto-matched. Please review and resolve before endorsing.');
        }

        return $this->finalizeEndorsement($user, $data, $participants, $request, $matchResults);
    }

    public function showMatchingReview(Request $request)
    {
        $pending = $request->session()->get('pending_match_review');
        if (! $pending) {
            return redirect()->route('admin.certs.create')
                ->with('info', 'No pending matching review. Please create a new certificate package.');
        }

        $allRecipients = Recipient::orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.certificates.matching-review', [
            'participants' => $pending['participants'],
            'results' => $pending['results'],
            'data' => $pending['data'],
            'allRecipients' => $allRecipients,
        ]);
    }

    public function resolveMatching(Request $request)
    {
        $pending = $request->session()->get('pending_match_review');
        if (! $pending) {
            return redirect()->route('admin.certs.create')
                ->with('info', 'Session expired. Please re-upload the participants file.');
        }

        $resolutions = $request->input('matches', []);

        foreach ($pending['results'] as $i => &$result) {
            $resolution = $resolutions[$i] ?? 'skip';

            if ($result['recipient_id'] !== null) {
                continue; // Already matched — don't override
            }

            if (str_starts_with($resolution, 'accept_')) {
                $recipientId = (int) substr($resolution, 7);
                $result['recipient_id'] = $recipientId;
                $result['confidence'] = 'manual';
                $result['ambiguous'] = false;
            } elseif ($resolution === 'create') {
                $participant = $pending['participants'][$i];
                $recipient = Recipient::create([
                    'name' => trim((string) ($participant['name'] ?? 'Unknown')),
                    'email' => trim((string) ($participant['email'] ?? '')) ?: null,
                    'contact_number' => trim((string) ($participant['contact_number'] ?? '')) ?: null,
                    'gender' => trim((string) ($participant['gender'] ?? '')) ?: null,
                    'password' => null,
                ]);
                $result['recipient_id'] = $recipient->id;
                $result['confidence'] = 'created';
                $result['ambiguous'] = false;
            }
        }
        unset($result);

        $request->session()->put('pending_match_review', $pending);

        $user = $request->user();
        $matchResults = $pending['results'];
        $data = $pending['data'];
        $participants = $pending['participants'];

        return $this->finalizeEndorsement($user, $data, $participants, $request, $matchResults);
    }

    private function finalizeEndorsement(User $user, array $data, array $participants, Request $request, array $matchResults)
    {
        $pending = $request->session()->pull('pending_match_review');

        if ($pending) {
            $participantsFilePath = $pending['participants_file_path'];
            $templatePdfPath = $pending['template_pdf_path'];
        } else {
            $participantsFile = $request->file('participants_file');
            $participantsExt = strtolower((string) $participantsFile->getClientOriginalExtension());
            $participantsFilePath = $participantsFile->storeAs(
                'certificate-endorsements/participants',
                'participants_' . Str::uuid() . '.' . $participantsExt,
                'local'
            );

            $templatePdfPath = $this->storeTemplatePdfForRequest($data, $request, 'certificate-endorsements/templates');
        }

        $payload = $this->buildTrainingPayload($data);
        $payload['recipient_matches'] = array_map(fn ($r) => $r['recipient_id'] ?? null, $matchResults);

        $endorsement = CertificateEndorsement::create([
            'status' => CertificateEndorsement::STATUS_ENDORSED,
            'submitted_by' => $user->id,
            'participants_count' => count($participants),
            'participants_file_path' => $participantsFilePath,
            'template_pdf_path' => $templatePdfPath,
            'payload' => $payload,
        ]);

        $this->notifyRegionalDirectorMessengerOnEndorsement($endorsement, $user);
        $this->notifyRegionalDirectorTelegramOnEndorsement($endorsement, $user);

        return redirect()
            ->route('admin.certs.index')
            ->with('success', 'Certificate package endorsed to Regional Director for approval.');
    }

    public function approveEndorsement(Request $request, int $id)
    {
        $user = $request->user();
        $this->ensureRegionalDirectorAction($user);

        $endorsement = CertificateEndorsement::findOrFail($id);
        if ($endorsement->status !== CertificateEndorsement::STATUS_ENDORSED) {
            return back()->with('success', 'Only endorsed certificate requests can be approved.');
        }
        $reviewKey = $this->endorsementParticipantsReviewedSessionKey($endorsement->id);
        if (!$request->session()->get($reviewKey, false)) {
            return back()->withErrors([
                'Please review participants first. Download the CSV/XLSX file before approving this package.',
            ]);
        }

        try {
            $participants = $this->parseParticipantStoragePath((string) $endorsement->participants_file_path);
            if (empty($participants)) {
                return back()->withErrors(['Unable to process participants file for this request.']);
            }
        } catch (\Throwable $e) {
            return back()->withErrors([$e->getMessage()]);
        }

        // Claim before dispatching. A double-click races on this conditional
        // update and only one caller wins, so only one batch is ever queued.
        if (! $this->generation->claim($endorsement, $user?->id)) {
            return back()->with('success', 'This certificate package is already being processed.');
        }

        try {
            $this->generation->dispatchGeneration($endorsement, $participants, $user?->id);
        } catch (\Throwable $e) {
            // dispatchGeneration releases the claim before rethrowing, so the
            // package is back in the queue and approvable again.
            return back()->withErrors([$e->getMessage()]);
        }

        $request->session()->forget($reviewKey);

        $count = count($participants);

        return back()->with(
            'info',
            "Generating {$count} certificate" . ($count === 1 ? '' : 's')
            . " in the background. Emails are queued once every certificate is generated;"
            . ' if any one fails the whole package is rolled back and returned here.'
        );
    }

    /**
     * Generation progress for one endorsement, for the approvals view to poll.
     */
    public function endorsementGenerationProgress(Request $request, int $id)
    {
        $this->ensureRegionalDirectorAction($request->user());

        $endorsement = CertificateEndorsement::findOrFail($id);

        return response()->json($this->generation->progress($endorsement));
    }

    public function rejectEndorsement(Request $request, int $id)
    {
        $user = $request->user();
        $this->ensureRegionalDirectorAction($user);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $endorsement = CertificateEndorsement::findOrFail($id);
        if ($endorsement->status !== CertificateEndorsement::STATUS_ENDORSED) {
            return back()->with('success', 'Only endorsed certificate requests can be rejected.');
        }

        $endorsement->update([
            'status' => CertificateEndorsement::STATUS_RD_REJECTED,
            'rd_rejected_by' => $user?->id,
            'rd_rejected_at' => now(),
            'rejection_reason' => trim((string) ($data['rejection_reason'] ?? '')) ?: null,
        ]);
        $request->session()->forget($this->endorsementParticipantsReviewedSessionKey($endorsement->id));

        return back()->with('success', 'Certificate endorsement request was rejected by the Regional Director.');
    }

    public function updateRegionalDirectorSignatory(Request $request)
    {
        $user = $request->user();
        $this->ensureRegionalDirectorAction($user);

        $data = $request->validate([
            'esign_enabled' => ['nullable', 'boolean'],
            'esign_file' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg', 'max:4096'],
        ]);

        $enabled = $request->boolean('esign_enabled');

        Setting::setValue(RegionalDirectorSignatory::KEY_ENABLED, $enabled ? '1' : '0', $user?->id);

        if ($request->hasFile('esign_file')) {
            $storedPath = $this->storeRegionalDirectorESignature($request->file('esign_file'));
            Setting::setValue(RegionalDirectorSignatory::KEY_PATH, $storedPath, $user?->id);
        }

        return back()->with('success', 'Regional Director signatory settings saved.');
    }

    private function storeRegionalDirectorESignature(UploadedFile $file): string
    {
        $disk = Storage::disk('public');
        foreach ($disk->files('certificates/signatories') as $existingPath) {
            if (str_starts_with(basename($existingPath), 'regional-director-signature.')) {
                $disk->delete($existingPath);
            }
        }

        $tmpDir = storage_path('app/tmp');
        @mkdir($tmpDir, 0777, true);
        $trimmedAbs = $tmpDir . '/rd-signature-' . Str::uuid() . '.png';

        if ($this->exportTrimmedSignatureImage($file, $trimmedAbs)) {
            $storedPath = 'certificates/signatories/regional-director-signature.png';
            $disk->put($storedPath, file_get_contents($trimmedAbs));
            @unlink($trimmedAbs);

            return $storedPath;
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());

        return $file->storeAs(
            'certificates/signatories',
            'regional-director-signature.' . $extension,
            'public'
        );
    }

    private function notifyRegionalDirectorMessengerOnEndorsement(
        CertificateEndorsement $endorsement,
        mixed $submitter
    ): void {
        if (!config('services.facebook_messenger.enabled', false)) {
            return;
        }

        $participantsCount = (int) ($endorsement->participants_count ?? 0);
        $notifyEvery = (bool) config('services.facebook_messenger.notify_on_every_endorsement', true);
        $bulkThreshold = max(1, (int) config('services.facebook_messenger.bulk_threshold', 50));
        if (!$notifyEvery && $participantsCount < $bulkThreshold) {
            return;
        }

        $pageAccessToken = trim((string) config('services.facebook_messenger.page_access_token', ''));
        $regionalDirectorPsid = trim((string) config('services.facebook_messenger.rd_psid', ''));
        if ($pageAccessToken === '' || $regionalDirectorPsid === '') {
            Log::warning('Messenger notification skipped: missing access token or RD PSID.');

            return;
        }

        $payload = is_array($endorsement->payload) ? $endorsement->payload : [];
        $trainingTitle = trim((string) ($payload['training_title'] ?? 'Untitled training'));
        $dateRange = $this->formatEndorsementDateRange($payload);
        $submittedBy = trim((string) ($submitter?->name ?? 'Unknown submitter'));
        $approvalsUrl = trim((string) config('services.facebook_messenger.approvals_url', ''));
        if ($approvalsUrl === '') {
            $approvalsUrl = url('/admin/certificates/approvals');
        }

        $message = "New certificate endorsement submitted.\n"
            . "Training: {$trainingTitle}\n"
            . "Schedule: {$dateRange}\n"
            . "Participants: {$participantsCount}\n"
            . "Submitted by: {$submittedBy}\n"
            . "Review queue: {$approvalsUrl}";

        $graphApiVersion = trim((string) config('services.facebook_messenger.graph_api_version', 'v22.0'));
        $graphApiVersion = ltrim($graphApiVersion, '/');
        $endpoint = "https://graph.facebook.com/{$graphApiVersion}/me/messages";

        try {
            $response = Http::asJson()
                ->timeout(12)
                ->post($endpoint, [
                    'recipient' => ['id' => $regionalDirectorPsid],
                    'messaging_type' => 'UPDATE',
                    'message' => ['text' => $message],
                    'access_token' => $pageAccessToken,
                ]);

            if (!$response->successful()) {
                Log::warning('Messenger notification failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'endorsement_id' => $endorsement->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Messenger notification threw an exception.', [
                'error' => $e->getMessage(),
                'endorsement_id' => $endorsement->id,
            ]);
        }
    }

    private function notifyRegionalDirectorTelegramOnEndorsement(
        CertificateEndorsement $endorsement,
        mixed $submitter
    ): void {
        $this->telegram->notifyOnEndorsement($endorsement, $submitter);
    }

    private function exportTrimmedSignatureImage(UploadedFile $file, string $destinationAbs): bool
    {
        if (!function_exists('imagecropauto')) {
            return false;
        }

        $sourceAbs = $file->getRealPath();
        if (!$sourceAbs) {
            return false;
        }

        $imageInfo = @getimagesize($sourceAbs);
        if (!$imageInfo) {
            return false;
        }

        $mime = strtolower((string) ($imageInfo['mime'] ?? ''));
        $sourceImage = match ($mime) {
            'image/png' => @imagecreatefrompng($sourceAbs),
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($sourceAbs),
            default => @imagecreatefromstring((string) @file_get_contents($sourceAbs)),
        };

        if (!$sourceImage) {
            return false;
        }

        imagealphablending($sourceImage, false);
        imagesavealpha($sourceImage, true);

        $croppedImage = false;
        if ($mime === 'image/png' && defined('IMG_CROP_TRANSPARENT')) {
            $croppedImage = @imagecropauto($sourceImage, IMG_CROP_TRANSPARENT);
        }
        if (!$croppedImage && defined('IMG_CROP_SIDES')) {
            $croppedImage = @imagecropauto($sourceImage, IMG_CROP_SIDES);
        }
        if (!$croppedImage && defined('IMG_CROP_WHITE')) {
            $croppedImage = @imagecropauto($sourceImage, IMG_CROP_WHITE);
        }

        $finalImage = $croppedImage ?: $sourceImage;
        if ($finalImage !== $sourceImage) {
            imagedestroy($sourceImage);
        }

        imagealphablending($finalImage, false);
        imagesavealpha($finalImage, true);

        $saved = @imagepng($finalImage, $destinationAbs);
        imagedestroy($finalImage);

        return $saved && is_file($destinationAbs);
    }

    /**
     * Generate a polished, gender-neutral certificate caption with a local
     * Ollama model. The caption appears beneath the recipient's printed name,
     * so it never includes the name and always uses "his/her" phrasing so a
     * single caption fits any recipient. Falls back to a deterministic,
     * template-built caption when Ollama is unreachable or disabled.
     */
    public function suggestCaption(Request $request)
    {
        if (!$this->canPrepareCertificate($request->user())) {
            abort(403, 'You are not allowed to draft certificate captions.');
        }

        $context = [
            'certificate_type' => $this->cleanCaptionField($request->input('certificate_type')),
            'recipient_type' => $this->cleanCaptionField($request->input('recipient_type')),
            'activity_type' => $this->cleanCaptionField($request->input('activity_type')),
            'title' => $this->cleanCaptionField($request->input('training_title')),
            'topic' => $this->cleanCaptionField($request->input('topic')),
            'venue' => $this->cleanCaptionField($request->input('venue')),
            'date_range' => $this->humanizeCaptionDateRange(
                $request->input('training_date_from'),
                $request->input('training_date_to')
            ),
            'given_clause' => $this->formatGivenClause($request->input('training_date_to')),
            'hours' => $this->cleanCaptionField($request->input('number_of_training_hours')),
            'tone' => $this->cleanCaptionField($request->input('tone')) ?: 'warm and dignified',
            // Background/purpose of the training typed in the AI panel.
            'context' => trim(mb_substr((string) $request->input('context', ''), 0, 500)),
            // Free-text instructions the issuer optionally types in the panel.
            'instructions' => trim(mb_substr((string) $request->input('instructions', ''), 0, 500)),
        ];

        $caption = $this->generateCaptionWithOllama($context);
        $source = 'ai';

        if ($caption === null) {
            $caption = $this->buildFallbackCaption($context);
            $source = 'fallback';
        }

        return response()->json([
            'caption' => $caption,
            'source' => $source,
        ]);
    }

    private function cleanCaptionField($value): string
    {
        $value = is_string($value) ? trim($value) : '';

        // Drop placeholder sentinels the form uses for unfilled "Others" rows.
        if ($value === '' || strcasecmp($value, 'Others') === 0 || strcasecmp($value, self::NOT_APPLICABLE) === 0) {
            return '';
        }

        return $value;
    }

    private function humanizeCaptionDateRange($from, $to): string
    {
        $from = is_string($from) ? trim($from) : '';
        $to = is_string($to) ? trim($to) : '';

        try {
            $start = $from !== '' ? \Carbon\Carbon::parse($from) : null;
            $end = $to !== '' ? \Carbon\Carbon::parse($to) : null;
        } catch (\Throwable $e) {
            return '';
        }

        if (!$start && !$end) {
            return '';
        }

        if ($start && $end && !$start->isSameDay($end)) {
            if ($start->isSameMonth($end) && $start->isSameYear($end)) {
                return $start->format('F j') . ' to ' . $end->format('j, Y');
            }

            return $start->format('F j, Y') . ' to ' . $end->format('F j, Y');
        }

        return ($start ?? $end)->format('F j, Y');
    }

    /**
     * Build the "Given this 26th day of May 2026" clause used to close the
     * citation. Uses the activity's end date, falling back to today.
     */
    private function formatGivenClause($dateTo): string
    {
        $dateTo = is_string($dateTo) ? trim($dateTo) : '';

        try {
            $date = $dateTo !== '' ? \Carbon\Carbon::parse($dateTo) : \Carbon\Carbon::now();
        } catch (\Throwable $e) {
            $date = \Carbon\Carbon::now();
        }

        return $date->format('jS') . ' day of ' . $date->format('F Y');
    }

    private function generateCaptionWithOllama(array $context): ?string
    {
        if (!config('services.ollama.enabled', true)) {
            return null;
        }

        $baseUrl = rtrim((string) config('services.ollama.base_url', 'http://localhost:11434'), '/');
        $model = (string) config('services.ollama.model', 'qwen2.5:3b');
        $timeout = (int) config('services.ollama.timeout', 30);

        $givenClause = $context['given_clause'] ?: $this->formatGivenClause(null);
        $venue = $context['venue'] ?: 'DOST Caraga, Ampayon, Butuan City';

        $details = collect([
            'Certificate Type' => $context['certificate_type'] ?: 'Certificate of Participation',
            'Recipient Role' => $context['recipient_type'] ?: 'Participant',
            'Activity Type' => $context['activity_type'],
            'Title' => $context['title'],
            'Topic' => $context['topic'],
            'Dates held' => $context['date_range'],
            'Venue' => $context['venue'],
            'Training Hours' => $context['hours'],
        ])->filter(fn ($v) => $v !== '' && $v !== null)
          ->map(fn ($v, $k) => "- {$k}: {$v}")
          ->implode("\n");

        $system = <<<'SYS'
ROLE
You generate the body text printed on official Certificates issued by the Department of Science and Technology (DOST). The recipient's name and the certificate's pre-printed preamble appear ABOVE your text; your output continues that preamble.

INPUT
You will be given some or all of: TITLE (event name), ROLE (e.g., Participant, Resource Speaker, Facilitator, Trainer, Lecturer, Evaluator), DATES, VENUE, CONTEXT CLAUSE, and the issuance date for the GIVEN clause. Use ONLY what is provided.

OUTPUT — ABSOLUTE RULES
1. Output EXACTLY two sentences, each ending in a single period. No labels, headings, line numbers, numbering, markdown, asterisks, bullets, surrounding quotation marks, or any preamble/commentary. Output the two sentences and nothing else.
2. NEVER write the recipient's name (it is printed above). Write in the third person and keep it gender-neutral: use "his/her" only. NEVER use he, she, they, them, you, your, Mr., Ms., or the person's name.
3. Sentence 1 begins with a LOWERCASE letter (it continues the pre-printed preamble). Sentence 2 begins with the capitalized word "Given".
4. Wrap TITLE in straight double quotation marks ("..."), reproduce it EXACTLY as provided, and do NOT shorten, paraphrase, or add an ellipsis. (Any "..." in the examples below only marks where a long title was trimmed for this prompt — never output an ellipsis.)
5. Use the correct article: "a" before a consonant sound, "an" before a vowel sound (e.g., "as an Evaluator", "as a Resource Speaker").
6. Do NOT invent facts, hours, dates, roles, or places. If a needed detail is missing, omit the clause that requires it (see FALLBACKS) — never guess.

DATE FORMAT
- In Sentence 1, use long form: "April 23, 2026"; a range as "April 23-24, 2026" or "April 23 to May 2, 2026".
- In the GIVEN clause (Sentence 2), use an ordinal: "Given this 23rd day of April 2026 at <VENUE>." Use the single issuance date provided (usually the last day of the event); never a range here.

CHOOSE THE FORMAT
- PARTICIPATION format: when ROLE is Participant/attendee, or no role is given.
- RECOGNITION format: for any contributor role (Resource Speaker, Facilitator, Trainer, Lecturer, Evaluator, Coordinator, Judge, etc.).

PARTICIPATION FORMAT
Sentence 1 — choose based on what is provided:
- Date and venue known: for actively participating during the "<TITLE>" held on <DATES> at <VENUE>.
- Context clause provided (with or without date/venue): for actively participating in the "<TITLE>" <CONTEXT CLAUSE>.
Sentence 2 (always): Given this <GIVEN CLAUSE> at <VENUE>.

RECOGNITION FORMAT
Sentence 1: for imparting his/her knowledge and expertise as <a/an> <ROLE> during the conduct of the "<TITLE>" held on <DATES> at <VENUE>.
Sentence 2 (always): Given this <GIVEN CLAUSE> at <VENUE>.

FALLBACKS (only when a detail is missing)
- No venue in Sentence 1: drop " at <VENUE>" and end after the date.
- No dates: use the context-clause variant, or drop "held on <DATES>".
- The GIVEN clause requires a place; if none is separately provided, reuse the event VENUE.

SELF-CHECK before output: exactly two sentences; no name / he / she / they / you; TITLE in double quotes and verbatim; Sentence 1 starts lowercase; Sentence 2 starts with "Given"; plain text only.

EXAMPLES
Participation (date and venue): for actively participating during the "Digital Transformation for MSMEs" held on April 23, 2026 at Watergate Pavilion, Butuan City. Given this 23rd day of April 2026 at Watergate Pavilion, Butuan City.
Participation (context clause): for actively participating in the "Workshop on Vibe Coding" contributing to the continuing efforts in strengthening digital transformation in the region. Given this 26th day of May 2026 at DOST Caraga - AMCEN, Ampayon, Butuan City.
Recognition (Resource Speaker): for imparting his/her knowledge and expertise as a Resource Speaker during the conduct of the "Training on Selection and Chemical Analysis of Metals" held on June 3, 2026 at Butuan City, Agusan del Norte. Given this 3rd day of June 2026 at Butuan City, Agusan del Norte.
SYS;

        $certType = $context['certificate_type'] ?: 'participation';
        $prompt = "Write the citation for the certification of {$certType}"
            . " with the event " . ($context['title'] ?: 'the training')
            . " conducted on " . ($context['date_range'] ?: 'the scheduled date')
            . " and conducted at {$venue}.";

        if (!empty($context['context'])) {
            $prompt .= " This is the context: " . $context['context'];
        }

        $prompt .= "\n\nAdditional details:\n" . $details
            . "\n\nThe FINAL sentence must read exactly: Given this {$givenClause} at {$venue}.";

        if (!empty($context['instructions'])) {
            $prompt .= "\n\nAdditional instructions from the issuer (follow them, but keep all the rules above): "
                . $context['instructions'];
        }

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post($baseUrl . '/api/generate', [
                    'model' => $model,
                    'system' => $system,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.65,
                        'top_p' => 0.9,
                        'num_predict' => 380,
                        'repeat_penalty' => 1.15,
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('Ollama caption request failed', ['status' => $response->status()]);
                return null;
            }

            $caption = $this->normalizeGeneratedCaption((string) $response->json('response', ''));

            return $caption !== '' ? $caption : null;
        } catch (\Throwable $e) {
            Log::warning('Ollama caption request errored', ['message' => $e->getMessage()]);
            return null;
        }
    }

    private function normalizeGeneratedCaption(string $caption): string
    {
        $caption = trim($caption);

        // Strip code fences, surrounding quotes, and any leftover markdown
        // emphasis the model may emit despite instructions.
        $caption = preg_replace('/```[a-z]*\s*|\s*```/i', '', $caption);
        $caption = preg_replace('/[*_#`>]+/', '', $caption);
        $caption = trim($caption, " \t\n\r\"'");

        // Collapse stray whitespace/newlines into clean single-spaced prose.
        $caption = preg_replace('/\s*\n\s*/', ' ', $caption);
        $caption = preg_replace('/[ \t]{2,}/', ' ', $caption);

        // Insert a double line-break before the closing "Given this…" sentence.
        $caption = preg_replace('/\s+(?=Given this\b)/i', '<br><br>', $caption);

        return trim($caption);
    }

    private function buildFallbackCaption(array $context): string
    {
        $role = $context['recipient_type'] ?: 'Participant';
        $venue = $context['venue'] ?: 'DOST Caraga, Ampayon, Butuan City';
        $givenClause = $context['given_clause'] ?: $this->formatGivenClause(null);
        $isParticipant = stripos($role, 'participant') !== false
            || stripos($context['certificate_type'] ?? '', 'participation') !== false;

        $activity = trim(($context['activity_type'] ? $context['activity_type'] . ' ' : '') . $context['title']);

        if ($isParticipant) {
            // Participation format — 2 sentences, lowercase opening.
            $title = $context['title'] !== '' ? '"' . $context['title'] . '"' : '"the activity"';
            $s1 = "for actively participating during the {$title}"
                . ($context['date_range'] !== '' ? ' held on ' . $context['date_range'] : '')
                . ($context['venue'] !== '' ? ', held at ' . $context['venue'] : '') . '.';
            $s2 = "Given this {$givenClause} at {$venue}.";
            return $s1 . '<br><br>' . $s2;
        }

        // Recognition format — 2 sentences, lowercase opening.
        $title = $context['title'] !== '' ? '"' . $context['title'] . '"' : '"the activity"';
        $s1 = "for imparting his/her knowledge and expertise as a {$role} during the conduct of the {$title}"
            . ($context['date_range'] !== '' ? ' held on ' . $context['date_range'] : '')
            . ($context['venue'] !== '' ? ' at ' . $context['venue'] : '') . '.';
        $s2 = "Given this {$givenClause} at {$venue}.";
        return $s1 . '<br><br>' . $s2;
    }

    public function livePreview(Request $request)
    {
        if (!$this->canPrepareCertificate($request->user())) {
            abort(403, 'You are not allowed to preview certificate requests.');
        }

        $templateSource = (string) $request->input('template_source', ($request->hasFile('certificate_pdf_shared') ? 'custom' : 'default'));
        if (!in_array($templateSource, ['default', 'custom'], true)) {
            return response()->json(['message' => 'Invalid template source.'], 422);
        }

        $certificateType = (string) ($request->input('certificate_type') ?: ($this->automaticCertificateTypeByRecipientType()[(string) $request->input('recipient_type', '')] ?? 'Certificate of Participation'));

        if ($templateSource === 'custom' && !$request->hasFile('certificate_pdf_shared')) {
            return response()->json(['message' => 'Please upload the certificate template PDF when choosing custom upload.'], 422);
        }

        try {
            if ($templateSource === 'custom') {
                $sourceAbs = $request->file('certificate_pdf_shared')->getRealPath();
            } else {
                $sourceAbs = $this->defaultTemplatePathForCertificateType($certificateType);
            }
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $participantName = $this->resolveLivePreviewParticipantName($request);
        $previewCode = 'PREVIEW-' . strtoupper(Str::random(6));
        $verifyUrl = $this->generator->buildVerifyUrl((string) Str::uuid());

        try {
            $pdfContent = $this->generator->renderStampedPdf(
                $sourceAbs,
                $participantName,
                self::STANDARD_NAME_POS_X,
                self::STANDARD_NAME_POS_Y,
                self::STANDARD_NAME_FONT_SIZE,
                self::STANDARD_NAME_FONT_FAMILY,
                true,
                $previewCode,
                $verifyUrl,
                true,
                $this->generator->sanitizeCaptionMarkup($request->input('caption_text')),
                (string) $request->input('caption_alignment', 'center')
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificate-live-preview.pdf"',
        ]);
    }

    public function preview(Request $request)
    {
        if (!$this->canPrepareCertificate($request->user())) {
            abort(403, 'You are not allowed to preview certificate requests.');
        }

        [$data, $participants] = $this->validatedCertificatePayload($request);
        $first = $participants[0] ?? null;
        if (!$first) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Please provide at least one participant.'], 422);
            }

            return back()->withErrors(['Please provide at least one participant.'])->withInput();
        }

        if (($data['template_source'] ?? null) === 'custom' && $request->hasFile('certificate_pdf_shared')) {
            $sourceAbs = $request->file('certificate_pdf_shared')->getRealPath();
        } else {
            $sourceAbs = $this->defaultTemplatePathForCertificateType((string) ($data['certificate_type'] ?? ''));
        }

        $previewCode = 'PREVIEW-' . strtoupper(Str::random(6));
        $verifyUrl = $this->generator->buildVerifyUrl((string) Str::uuid());

        try {
            $pdfContent = $this->generator->renderStampedPdf(
                $sourceAbs,
                $first['name'],
                self::STANDARD_NAME_POS_X,
                self::STANDARD_NAME_POS_Y,
                self::STANDARD_NAME_FONT_SIZE,
                self::STANDARD_NAME_FONT_FAMILY,
                true,
                $previewCode,
                $verifyUrl,
                true,
                $data['caption_text'] ?? null,
                $data['caption_alignment'] ?? 'center'
            );
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()
                ->withErrors([$e->getMessage()])
                ->withInput();
        }

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificate-preview.pdf"',
        ]);
    }

    private function validatedCertificatePayload(Request $request): array
    {
        $input = $request->all();
        if (empty($input['template_source'])) {
            $input['template_source'] = $request->hasFile('certificate_pdf_shared') ? 'custom' : 'default';
        }
        $automaticCertificateType = $this->automaticCertificateTypeByRecipientType()[(string) ($input['recipient_type'] ?? '')] ?? null;
        if ($automaticCertificateType !== null) {
            $input['certificate_type'] = $automaticCertificateType;
        }

        $validator = Validator::make($input, [
            'training_title' => ['required', 'string', 'max:255'],
            'activity_type' => ['required', Rule::in($this->activityTypes())],
            'activity_type_other' => ['exclude_unless:activity_type,Others', 'required', 'string', 'max:255', 'regex:/.*\S.*/'],
            'certificate_type' => ['required', Rule::in($this->certificateTypes())],
            'recipient_type' => ['required', Rule::in($this->recipientTypes())],
            'recipient_type_other' => ['exclude_unless:recipient_type,Others', 'required', 'string', 'max:255', 'regex:/.*\S.*/'],
            'venue' => ['required', 'string', 'max:255'],
            'topic' => ['required', Rule::in($this->topics())],
            'topic_other' => ['exclude_unless:topic,Others', 'required', 'string', 'max:255', 'regex:/.*\S.*/'],
            'training_date_from' => ['required', 'date'],
            'training_date_to' => ['required', 'date', 'after_or_equal:training_date_from'],
            'number_of_training_hours' => ['required', 'integer', 'min:1', 'max:1000'],
            'dost_program' => ['required', Rule::in($this->dostPrograms())],
            'dost_program_other' => ['exclude_unless:dost_program,Others', 'required', 'string', 'max:255', 'regex:/.*\S.*/'],
            'pillar' => ['required', Rule::in($this->pillars())],
            'dost_project' => ['required', Rule::in(array_merge(
                array_keys($this->dostProjectCodeMap()),
                $this->setupOfficeProvinces(),
                [self::CUSTOM_DOST_PROJECT_OPTION]
            ))],
            'dost_project_other' => ['exclude_unless:dost_project,' . self::CUSTOM_DOST_PROJECT_OPTION, 'required', 'string', 'max:255', 'regex:/.*\S.*/'],
            'source_of_funds' => ['required', Rule::in($this->sourceOfFundsOptions())],
            'training_budget' => ['nullable', 'numeric', 'min:0'],
            'expected_number_of_participants' => ['nullable', 'integer', 'min:1'],
            'issuing_office' => ['required', 'string', 'max:255'],
            'participants_file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:' . self::PARTICIPANTS_FILE_MAX_KB],
            'template_source' => ['required', 'in:default,custom'],
            'certificate_pdf_shared' => ['required_if:template_source,custom', 'file', 'mimes:pdf', 'max:' . self::CERTIFICATE_TEMPLATE_MAX_KB],
            'caption_text' => ['nullable', 'string'],
            'caption_alignment' => ['nullable', 'string', 'in:left,center,right,justify'],
        ], [
            'participants_file.uploaded' => 'The participants file failed to upload due to a server upload limit. Please reduce file size and try again.',
            'participants_file.max' => 'The participants file must not be greater than ' . (int) floor(self::PARTICIPANTS_FILE_MAX_KB / 1024) . ' MB.',
            'certificate_pdf_shared.required_if' => 'Please upload the certificate template PDF when choosing custom upload.',
            'certificate_pdf_shared.mimes' => 'The certificate template must be a valid PDF file.',
            'certificate_pdf_shared.uploaded' => 'The certificate template PDF failed to upload due to a server upload limit (PHP/Nginx). Please compress the PDF or contact admin to increase upload limits.',
            'certificate_pdf_shared.max' => 'The certificate template PDF must not be greater than ' . (int) floor(self::CERTIFICATE_TEMPLATE_MAX_KB / 1024) . ' MB.',
        ]);

        $data = $validator->validate();
        $data['caption_text'] = $this->generator->sanitizeCaptionMarkup($data['caption_text'] ?? null);
        $automaticCertificateType = $this->automaticCertificateTypeByRecipientType()[$data['recipient_type']] ?? null;
        if ($automaticCertificateType !== null) {
            $data['certificate_type'] = $automaticCertificateType;
        }
        if ($data['activity_type'] === 'Others') {
            $data['activity_type'] = trim((string) $data['activity_type_other']);
        }
        if ($data['recipient_type'] === 'Others') {
            $data['recipient_type'] = trim((string) $data['recipient_type_other']);
        }
        if ($data['topic'] === 'Others') {
            $data['topic'] = trim((string) $data['topic_other']);
        }
        if ($this->isNationalRegularProgram($data['dost_program'])) {
            $data['setup_office_province'] = self::NOT_APPLICABLE;
            $data['dost_project'] = self::NOT_APPLICABLE;
            $data['project_code'] = self::NOT_APPLICABLE;
            $data['source_of_funds'] = $this->regularFundsLabel();
        } elseif ($this->isSetupProgram($data['dost_program'])) {
            if (!in_array($data['dost_project'], $this->setupOfficeProvinces(), true)) {
                throw ValidationException::withMessages([
                    'dost_project' => 'Please select a valid DOST Office/Province for SETUP.',
                ]);
            }
            $data['setup_office_province'] = $data['dost_project'];
            $data['dost_project'] = self::NOT_APPLICABLE;
            $data['project_code'] = self::NOT_APPLICABLE;
        } else {
            $usesCustomDostProject = $data['dost_project'] === self::CUSTOM_DOST_PROJECT_OPTION;

            if ($usesCustomDostProject && !$this->isSscpProgram($data['dost_program'])) {
                throw ValidationException::withMessages([
                    'dost_project' => 'Others, please specify is only available for SSCP.',
                ]);
            }

            if (in_array($data['dost_project'], $this->setupOfficeProvinces(), true)) {
                throw ValidationException::withMessages([
                    'dost_project' => 'Please select a valid DOST Project.',
                ]);
            }
            $data['setup_office_province'] = self::NOT_APPLICABLE;
            if ($usesCustomDostProject) {
                $data['dost_project'] = trim((string) $data['dost_project_other']);
                $data['project_code'] = null;
            } else {
                if (!$this->isDostProjectAllowedForProgram($data['dost_program'], $data['dost_project'])) {
                    throw ValidationException::withMessages([
                        'dost_project' => 'Please select a DOST Project under the chosen DOST Program.',
                    ]);
                }
                $data['project_code'] = $this->dostProjectCodeMap()[$data['dost_project']] ?? null;
                if (!$data['project_code']) {
                    throw ValidationException::withMessages([
                        'dost_project' => 'Please select a valid DOST Project.',
                    ]);
                }
            }
            if ($this->isProjectFundProgram($data['dost_program'])) {
                $data['source_of_funds'] = $this->projectFundsLabel();
            }
        }
        if ($data['dost_program'] === 'Others') {
            $data['dost_program'] = trim((string) $data['dost_program_other']);
        }

        $participants = $this->resolveParticipants($request, $data);
        if (empty($participants)) {
            throw ValidationException::withMessages([
                'participants_file' => 'Please upload a valid participants CSV/XLSX file with at least one participant.',
            ]);
        }

        return [$data, $participants];
    }

    private function buildTrainingPayload(array $data): array
    {
        return [
            'training_title' => $data['training_title'],
            'caption_text' => $data['caption_text'] ?? null,
            'caption_alignment' => $data['caption_alignment'] ?? 'center',
            'activity_type' => $data['activity_type'],
            'certificate_type' => $data['certificate_type'],
            'recipient_type' => $data['recipient_type'],
            'venue' => $data['venue'],
            'topic' => $data['topic'],
            'training_date_from' => $data['training_date_from'],
            'training_date_to' => $data['training_date_to'],
            'number_of_training_hours' => (int) $data['number_of_training_hours'],
            'dost_program' => $data['dost_program'],
            'setup_office_province' => $data['setup_office_province'],
            'pillar' => $data['pillar'],
            'dost_project' => $data['dost_project'],
            'project_code' => $data['project_code'],
            'source_of_funds' => $data['source_of_funds'],
            'training_budget' => isset($data['training_budget']) && $data['training_budget'] !== ''
                ? (float) $data['training_budget']
                : null,
            'expected_number_of_participants' => isset($data['expected_number_of_participants']) && $data['expected_number_of_participants'] !== ''
                ? (int) $data['expected_number_of_participants']
                : null,
            'issuing_office' => $data['issuing_office'],
        ];
    }

    private function generateCertificatesFromPayload(
        array $payload,
        array $participants,
        string $templatePath,
        bool $applyRegionalDirectorESign = false,
        ?int $endorsementId = null
    ): array
    {
        $this->generator->assertPayloadIsGeneratable($payload, $templatePath);

        // FPDI cannot read the compressed cross-reference streams used by PDF
        // 1.5+, which is what Word/Canva/Illustrator export. Normalising the
        // template once here means the per-participant loop below never falls
        // back to Ghostscript, which otherwise re-converts an identical file
        // once per certificate and dominates the request (~98% of the time).
        [$templatePath, $temporaryTemplatePath] = $this->generator->normalizeTemplateForFpdi($templatePath);

        $generatedCertificates = [];
        $recipientMatches = (array) ($payload['recipient_matches'] ?? []);
        try {
            foreach ($participants as $i => $participant) {
                $generatedCertificates[] = $this->generator->generateForParticipant(
                    $payload,
                    $participant,
                    $recipientMatches[$i] ?? null,
                    $templatePath,
                    $applyRegionalDirectorESign,
                    $endorsementId,
                    $i
                );
            }
        } finally {
            if ($temporaryTemplatePath !== null) {
                Storage::disk('local')->delete($temporaryTemplatePath);
            }
        }

        return $generatedCertificates;
    }

    private function parseParticipantStoragePath(string $path): array
    {
        $storage = Storage::disk('local');
        if (!$storage->exists($path)) {
            throw new \RuntimeException('Participants file for this request is missing in storage.');
        }

        $absolutePath = $storage->path($path);
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'csv' || $ext === 'txt') {
            return $this->parseDelimitedParticipants($absolutePath);
        }
        if ($ext === 'xlsx') {
            if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
                throw new \RuntimeException('XLSX import requires phpoffice/phpspreadsheet.');
            }

            return $this->parseXlsxParticipants($absolutePath);
        }

        throw new \RuntimeException('Unsupported participants file type.');
    }

    private function resolveLivePreviewParticipantName(Request $request): string
    {
        if ($request->hasFile('participants_file')) {
            try {
                $participants = $this->parseParticipantFile($request->file('participants_file'));
                $firstName = trim((string) ($participants[0]['name'] ?? ''));
                if ($firstName !== '') {
                    return $firstName;
                }
            } catch (\Throwable $e) {
                // Fall back to a stable sample name for live preview only.
            }
        }

        return 'Sample Participant';
    }

    private function resolveParticipants(Request $request, array $data): array
    {
        if ($request->hasFile('participants_file')) {
            $file = $request->file('participants_file');
            $ext = strtolower($file->getClientOriginalExtension());
            if ($ext === 'xlsx' && !class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'participants_file' => 'XLSX import requires phpoffice/phpspreadsheet. Use CSV for now or install the dependency.',
                ]);
            }
            $participants = $this->parseParticipantFile($file);
            if (!empty($participants)) {
                return $participants;
            }
        }

        $names = array_map('trim', $data['participant_name'] ?? []);
        $emails = $data['participant_email'] ?? [];
        $genders = $data['participant_gender'] ?? [];
        $ages = $data['participant_age'] ?? [];
        $blocks = $data['participant_block_lot_purok'] ?? [];
        $regions = $data['participant_region'] ?? [];
        $cities = $data['participant_city_municipality'] ?? [];
        $barangays = $data['participant_barangay'] ?? [];
        $provinces = $data['participant_province'] ?? [];
        $industries = $data['participant_industry'] ?? [];
        $industryOthers = $data['participant_industry_other'] ?? [];

        $rows = [];
        foreach ($names as $i => $name) {
            if ($name === '') {
                continue;
            }
            $email = trim((string) ($emails[$i] ?? ''));
            $gender = $genders[$i] ?? null;
            $gender = in_array($gender, ['Male', 'Female'], true) ? $gender : null;

            $ageRaw = $ages[$i] ?? null;
            $age = ($ageRaw === null || $ageRaw === '') ? null : (int) $ageRaw;

            $block = trim((string) ($blocks[$i] ?? ''));
            $region = trim((string) ($regions[$i] ?? ''));
            $city = trim((string) ($cities[$i] ?? ''));
            $barangay = trim((string) ($barangays[$i] ?? ''));
            $province = trim((string) ($provinces[$i] ?? ''));
            $industry = trim((string) ($industries[$i] ?? ''));
            $industryOther = trim((string) ($industryOthers[$i] ?? ''));
            if ($industry === 'Others' && $industryOther !== '') {
                $industry = $industryOther;
            } elseif ($industry === '') {
                $industry = null;
            }
            if ($province === '' || $province === null) {
                $province = $this->inferProvinceFromCityRegion($city, $region) ?? '';
            }

            $rows[] = [
                'name' => $name,
                'email' => $email !== '' ? $email : null,
                'gender' => $gender,
                'age' => $age,
                'block_lot_purok' => $block !== '' ? $block : null,
                'region' => $region !== '' ? $region : null,
                'city_municipality' => $city !== '' ? $city : null,
                'barangay' => $barangay !== '' ? $barangay : null,
                'province' => $province !== '' ? $province : null,
                'industry' => $industry !== '' ? $industry : null,
            ];
        }

        return array_values($rows);
    }

    private function parseParticipantFile($file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();
        if (!$path) {
            return [];
        }

        if ($ext === 'csv' || $ext === 'txt') {
            return $this->parseDelimitedParticipants($path);
        }

        if ($ext === 'xlsx') {
            return $this->parseXlsxParticipants($path);
        }

        return [];
    }

    private function parseDelimitedParticipants(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            return [];
        }

        $participants = [];
        $header = null;
        while (($row = fgetcsv($handle)) !== false) {
            $row = $this->normalizeImportedRow($row);
            $normalized = $this->normalizeHeaderRow($row);
            if ($header === null) {
                if ($this->rowHasName($normalized)) {
                    $header = $normalized;
                    continue;
                }
                $participants[] = $this->rowToParticipant($row);
                continue;
            }

            $participants[] = $this->rowToParticipant($this->combineRow($header, $row));
        }
        fclose($handle);

        return array_values(array_filter($participants, fn ($p) => !empty($p['name'])));
    }

    private function parseXlsxParticipants(string $path): array
    {
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        $participants = [];
        $header = null;
        foreach ($rows as $row) {
            $row = $this->normalizeImportedRow($row);
            $normalized = $this->normalizeHeaderRow($row);
            if ($header === null) {
                if ($this->rowHasName($normalized)) {
                    $header = $normalized;
                    continue;
                }
                $participants[] = $this->rowToParticipant($row);
                continue;
            }
            $participants[] = $this->rowToParticipant($this->combineRow($header, $row));
        }

        return array_values(array_filter($participants, fn ($p) => !empty($p['name'])));
    }

    private function normalizeImportedRow(array $row): array
    {
        return array_map(fn ($value) => $this->normalizeImportedValue($value), $row);
    }

    private function normalizeImportedValue(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            $value = substr($value, 3);
        }

        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        foreach (['Windows-1252', 'ISO-8859-1'] as $encoding) {
            $converted = @iconv($encoding, 'UTF-8//IGNORE', $value);
            if (is_string($converted) && $converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        $sanitized = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return is_string($sanitized) ? $sanitized : '';
    }

    private function normalizeHeaderRow(array $row): array
    {
        return array_map(function ($value) {
            $v = strtolower(trim((string) $value));
            return $v === '' ? null : $v;
        }, $row);
    }

    private function rowHasName(array $header): bool
    {
        $patterns = ['name', 'names', 'participant', 'participant name', 'participant_name'];
        foreach ($header as $cell) {
            if ($cell === null) {
                continue;
            }
            $v = strtolower(trim((string) $cell));
            if ($v === '') {
                continue;
            }
            if (in_array($v, $patterns, true)) {
                return true;
            }
            $tokens = preg_split('/[^a-z0-9]+/', $v);
            foreach ($tokens as $t) {
                if ($t === 'name' || $t === 'participant') {
                    return true;
                }
            }
        }
        return false;
    }

    private function combineRow(array $header, array $row): array
    {
        $combined = [];
        foreach ($row as $i => $value) {
            $combined[$header[$i] ?? $i] = $value;
        }
        return $combined;
    }

    private function rowToParticipant(array $row): array
    {
        $map = function ($keys, $default = null) use ($row) {
            foreach ((array) $keys as $key) {
                if (array_key_exists($key, $row)) {
                    return trim((string) $row[$key]);
                }
            }
            return $default;
        };

        $name = $map(['name', 'names', 'participant', 'participant name', 'participant_name']);
        $firstName = $map(['first_name', 'first name', 'firstname']);
        $middleInitial = $map(['middle_initial', 'middle initial', 'mi', 'm.i.']);
        $lastName = $map(['last_name', 'last name', 'lastname', 'surname']);

        if (($name === null || $name === '') && ($firstName !== '' || $lastName !== '')) {
            $middle = trim((string) $middleInitial);
            $middle = rtrim($middle, '.');
            $middle = $middle === '' ? '' : (mb_substr($middle, 0, 1) . '.');
            $assembled = trim((string) $firstName)
                . ($middle !== '' ? ' ' . $middle : '')
                . ' ' . trim((string) $lastName);
            $name = trim($assembled);
        }

        if ($name === null || $name === '') {
            $name = array_key_exists(0, $row) ? trim((string) $row[0]) : null;
        }

        $email = $map(['email', 'e-mail', 'participant_email', 'participant email']);
        $offset = 0;
        if (($email === null || $email === '') && array_key_exists(1, $row)) {
            $candidate = trim((string) $row[1]);
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                $email = $candidate;
                $offset = 1;
            }
        }
        $gender = $map(['gender', 'sex']);
        if (($gender === null || $gender === '') && array_key_exists(1 + $offset, $row)) {
            $gender = trim((string) $row[1 + $offset]);
        }
        $gender = in_array($gender, ['Male', 'Female'], true) ? $gender : null;
        $ageRaw = $map(['age', 'age_range', 'age range']);
        if (($ageRaw === null || $ageRaw === '') && array_key_exists(2 + $offset, $row)) {
            $ageRaw = trim((string) $row[2 + $offset]);
        }
        $age = null;
        if ($ageRaw !== null && $ageRaw !== '' && is_numeric($ageRaw)) {
            $age = (int) $ageRaw;
        }
        $region = $map(['region']);
        $province = $map(['province', 'state', 'state/province', 'province/state', 'state_province', 'state province', 'province state']);
        $city = $map(['city_municipality', 'city/municipality', 'city municipality', 'city', 'municipality']);
        $barangay = $map(['barangay', 'brgy']);
        $block = $map(['block', 'lot', 'purok', 'block_lot_purok', 'block/lot/purok', 'block lot purok']);
        $industry = $map([
            'industry',
            'sector',
            'affiliation/sector',
            'affiliation_sector',
            'affiliation sector',
            'affiliation',
        ]);
        $industryOther = $map(['industry_other', 'other_industry']);

        // positional fallbacks using the official column order:
        // Name, Email, Gender, Age, Industry, Region, State/Province, City/Municipality, Barangay, Block/Lot/Purok
        if (($industry === null || $industry === '') && array_key_exists(3 + $offset, $row)) {
            $industry = trim((string) $row[3 + $offset]);
        }
        if (($region === null || $region === '') && array_key_exists(4 + $offset, $row)) {
            $region = trim((string) $row[4 + $offset]);
        }
        if (($province === null || $province === '') && array_key_exists(5 + $offset, $row)) {
            $province = trim((string) $row[5 + $offset]);
        }
        if (($city === null || $city === '') && array_key_exists(6 + $offset, $row)) {
            $city = trim((string) $row[6 + $offset]);
        }
        if (($barangay === null || $barangay === '') && array_key_exists(7 + $offset, $row)) {
            $barangay = trim((string) $row[7 + $offset]);
        }
        if (($block === null || $block === '') && array_key_exists(8 + $offset, $row)) {
            $block = trim((string) $row[8 + $offset]);
        }

        // legacy positional support (previous layouts): Region, Province, City/Municipality, Barangay, Block/Lot/Purok, Industry
        if (($region === null || $region === '') && array_key_exists(3 + $offset, $row)) {
            $region = trim((string) $row[3 + $offset]);
        }
        if (($province === null || $province === '') && array_key_exists(4 + $offset, $row)) {
            $province = trim((string) $row[4 + $offset]);
        }
        if (($city === null || $city === '') && array_key_exists(5 + $offset, $row)) {
            $city = trim((string) $row[5 + $offset]);
        }
        if (($barangay === null || $barangay === '') && array_key_exists(6 + $offset, $row)) {
            $barangay = trim((string) $row[6 + $offset]);
        }
        if (($block === null || $block === '') && array_key_exists(7 + $offset, $row)) {
            $block = trim((string) $row[7 + $offset]);
        }
        if (($industry === null || $industry === '') && array_key_exists(8 + $offset, $row)) {
            $industry = trim((string) $row[8 + $offset]);
        }
        if (($industry === 'Others' || $industry === '') && $industryOther !== null && $industryOther !== '') {
            $industry = $industryOther;
        }
        if (($province === null || $province === '') && $city !== null && $region !== null) {
            $province = $this->inferProvinceFromCityRegion($city, $region);
        }

        return [
            'name' => $name,
            'email' => $email !== '' ? $email : null,
            'gender' => $gender,
            'age' => $age,
            'block_lot_purok' => $block !== '' ? $block : null,
            'region' => $region !== '' ? $region : null,
            'city_municipality' => $city !== '' ? $city : null,
            'barangay' => $barangay !== '' ? $barangay : null,
            'province' => $province !== '' ? $province : null,
            'industry' => $industry !== '' ? $industry : null,
        ];
    }

    private function inferProvinceFromCityRegion(?string $city, ?string $region): ?string
    {
        if (!$city || !$region) {
            return null;
        }

        static $psgc = null;
        if ($psgc === null) {
            $path = resource_path('data/psgc.json');
            if (!file_exists($path)) {
                $path = public_path('data/psgc.json');
            }
            if (!file_exists($path)) {
                return null;
            }
            $psgc = json_decode(file_get_contents($path), true) ?: [];
        }

        foreach ($psgc as $regionName => $regionData) {
            if (!is_array($regionData)) {
                continue;
            }
            if (strcasecmp($regionName, $region) !== 0) {
                continue;
            }
            foreach ($regionData as $provName => $provData) {
                if (!is_array($provData) || $provName === 'population') {
                    continue;
                }
                foreach ($provData as $cityName => $cityData) {
                    if (!is_array($cityData) || in_array($cityName, ['population', 'class', 'cityClass'], true)) {
                        continue;
                    }
                    if (strcasecmp($cityName, $city) === 0) {
                        return $provName;
                    }
                }
            }
        }

        return null;
    }

    private function formatEndorsementDateRange(array $payload): string
    {
        return $this->telegram->formatDateRange($payload);
    }

    private function canPrepareCertificate(?User $user): bool
    {
        return $this->isRegionalDirector($user) || $this->canEndorseCertificates($user);
    }

    private function canEndorseCertificates(?User $user): bool
    {
        return $user ? $user->hasAnyRole(User::endorserRoles()) : false;
    }

    private function canDownloadCertificates(?User $user): bool
    {
        return $user ? ($user->isRegionalDirector() || $user->hasRole(User::ROLE_ORGANIZER)) : false;
    }

    private function canViewAnalytics(?User $user): bool
    {
        return $user ? ($user->isRegionalDirector() || $user->hasRole(User::ROLE_ORGANIZER)) : false;
    }

    private function isRegionalDirector(?User $user): bool
    {
        return $user ? $user->isRegionalDirector() : false;
    }

    private function ensureRegionalDirectorAction(?User $user): void
    {
        if (!$this->isRegionalDirector($user)) {
            abort(403, 'Only Regional Director can approve or generate certificates.');
        }
    }

    private function ensureCertificateDownloadAccess(?User $user): void
    {
        if (!$this->canDownloadCertificates($user)) {
            abort(403, 'Only Regional Director or Organizer can download certificates.');
        }
    }

    public function viewEndorsementTemplate(Request $request, int $id)
    {
        $this->ensureRegionalDirectorAction($request->user());

        $endorsement = CertificateEndorsement::findOrFail($id);
        if (empty($endorsement->template_pdf_path)) {
            abort(404, 'Uploaded template PDF not available.');
        }

        $storage = $this->resolveCertificateStorage((string) $endorsement->template_pdf_path);
        if (!$storage) {
            abort(404, 'Uploaded template PDF is missing in storage.');
        }

        return response()->file($storage['absolute'], [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->endorsementPdfFilename($endorsement, 'uploaded-template') . '"',
        ]);
    }

    public function previewEndorsement(Request $request, int $id)
    {
        $this->ensureRegionalDirectorAction($request->user());

        $endorsement = CertificateEndorsement::findOrFail($id);
        if (empty($endorsement->template_pdf_path)) {
            abort(404, 'Uploaded template PDF not available for preview.');
        }

        $storage = $this->resolveCertificateStorage((string) $endorsement->template_pdf_path);
        if (!$storage) {
            abort(404, 'Uploaded template PDF is missing in storage.');
        }

        $firstParticipantName = $this->firstEndorsementParticipantName($endorsement);
        $previewCode = 'PREVIEW-' . strtoupper(Str::random(6));
        $verifyUrl = $this->generator->buildVerifyUrl((string) Str::uuid());

        // The caption lives in the endorsement payload (see buildTrainingPayload),
        // not as a column on the endorsement row, so read it from there to match
        // what the final generated certificate prints on approval.
        $endorsementPayload = (array) $endorsement->payload;

        try {
            $pdfContent = $this->generator->renderStampedPdf(
                $storage['absolute'],
                $firstParticipantName,
                self::STANDARD_NAME_POS_X,
                self::STANDARD_NAME_POS_Y,
                self::STANDARD_NAME_FONT_SIZE,
                self::STANDARD_NAME_FONT_FAMILY,
                true,
                $previewCode,
                $verifyUrl,
                true,
                $endorsementPayload['caption_text'] ?? null,
                $endorsementPayload['caption_alignment'] ?? 'center'
            );
        } catch (\Throwable $e) {
            report($e);
            abort(422, $e->getMessage());
        }

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->endorsementPdfFilename($endorsement, 'preview') . '"',
        ]);
    }

    public function downloadEndorsementParticipants(Request $request, int $id): StreamedResponse
    {
        $this->ensureRegionalDirectorAction($request->user());

        $endorsement = CertificateEndorsement::findOrFail($id);
        $path = (string) ($endorsement->participants_file_path ?? '');
        if ($path === '') {
            abort(404, 'Participants file not available for this endorsement request.');
        }

        $storage = Storage::disk('local');
        if (!$storage->exists($path)) {
            abort(404, 'Participants file is missing in storage.');
        }

        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt', 'xlsx'], true)) {
            abort(400, 'Unsupported participants file type.');
        }
        $downloadExt = $ext === 'txt' ? 'csv' : $ext;

        $payload = is_array($endorsement->payload) ? $endorsement->payload : [];
        $titleSlug = Str::slug((string) ($payload['training_title'] ?? 'certificate-package'));
        if ($titleSlug === '') {
            $titleSlug = 'certificate-package';
        }

        $downloadName = 'participants_' . $titleSlug . '_endorsement_' . $endorsement->id . '.' . $downloadExt;
        $request->session()->put($this->endorsementParticipantsReviewedSessionKey($endorsement->id), true);
        return $storage->download($path, $downloadName);
    }

    public function download(Request $request, int $id): StreamedResponse
    {
        $this->ensureCertificateDownloadAccess($request->user());

        $cert = Certificate::findOrFail($id);
        if (empty($cert->stamped_pdf_path)) {
            abort(404, 'Stamped PDF not available.');
        }
        $storage = $this->resolveCertificateStorage((string) $cert->stamped_pdf_path);
        if (!$storage) {
            abort(404, 'File missing in storage.');
        }

        // Force download with a clean filename
        $downloadName = $cert->certificate_code . '.pdf';
        return $storage['disk']->download($storage['path'], $downloadName);
    }

    public function downloadGroup(Request $request)
    {
        $this->ensureCertificateDownloadAccess($request->user());

        if (!class_exists(\ZipArchive::class)) {
            abort(500, 'ZIP extension not available.');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date'],
            'office' => ['required', 'string', 'max:255'],
        ]);

        $certs = Certificate::where('training_title', $data['title'])
            ->whereDate('training_date', $data['date_from'])
            ->where(function ($query) use ($data) {
                $query->whereDate('training_date_to', $data['date_to']);
                if ($data['date_from'] === $data['date_to']) {
                    $query->orWhereNull('training_date_to');
                }
            })
            ->where('issuing_office', $data['office'])
            ->get();

        if ($certs->isEmpty()) {
            abort(404, 'No certificates found for this training.');
        }

        $zipDir = storage_path('app/tmp');
        @mkdir($zipDir, 0777, true);
        $zipPath = $zipDir . '/' . Str::uuid() . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Unable to create ZIP file.');
        }

        $added = 0;
        foreach ($certs as $cert) {
            if (empty($cert->stamped_pdf_path)) {
                continue;
            }
            $storage = $this->resolveCertificateStorage((string) $cert->stamped_pdf_path);
            if (!$storage) {
                continue;
            }
            $zip->addFile($storage['absolute'], $cert->certificate_code . '.pdf');
            $added++;
        }

        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);
            abort(404, 'No stamped PDFs available for this training.');
        }

        $zipName = 'certificates_' . Str::slug($data['title']) . '_' . $data['date_from'] . '_to_' . $data['date_to'] . '.zip';
        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }

    private function resolveCertificateStorage(string $path): ?array
    {
        if ($path === '') {
            return null;
        }

        $localDisk = Storage::disk('local');
        if ($localDisk->exists($path)) {
            return [
                'disk' => $localDisk,
                'path' => $path,
                'absolute' => $localDisk->path($path),
            ];
        }

        $publicDisk = Storage::disk('public');
        if ($publicDisk->exists($path)) {
            return [
                'disk' => $publicDisk,
                'path' => $path,
                'absolute' => $publicDisk->path($path),
            ];
        }

        return null;
    }

    private function endorsementParticipantsReviewedSessionKey(int $endorsementId): string
    {
        return 'cert_endorsements.participants_reviewed.' . $endorsementId;
    }

    private function firstEndorsementParticipantName(CertificateEndorsement $endorsement): string
    {
        if (empty($endorsement->participants_file_path)) {
            abort(404, 'Participants file not available for preview.');
        }

        try {
            $participants = $this->parseParticipantStoragePath((string) $endorsement->participants_file_path);
        } catch (\Throwable $e) {
            abort(422, 'Unable to read participants file for preview.');
        }

        foreach ($participants as $participant) {
            $name = trim((string) ($participant['name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        abort(422, 'No participant name found for PDF preview.');
    }

    private function endorsementPdfFilename(CertificateEndorsement $endorsement, string $suffix): string
    {
        $payload = is_array($endorsement->payload) ? $endorsement->payload : [];
        $titleSlug = Str::slug((string) ($payload['training_title'] ?? 'certificate-package'));
        if ($titleSlug === '') {
            $titleSlug = 'certificate-package';
        }

        return $titleSlug . '_endorsement_' . $endorsement->id . '_' . $suffix . '.pdf';
    }
}
