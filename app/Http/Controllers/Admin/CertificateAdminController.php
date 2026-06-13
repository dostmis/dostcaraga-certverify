<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AnchorCertificateOnHederaJob;
use App\Jobs\SendCertificateEmailJob;
use App\Models\Certificate;
use App\Models\CertificateEndorsement;
use App\Models\Recipient;
use App\Models\Setting;
use App\Models\User;
use App\Services\RecipientMatchingService;
use App\Support\PdfImageNormalizer;
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
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use setasign\Fpdi\Fpdi;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateAdminController extends Controller
{
    private const STANDARD_NAME_POS_X = 30.0;
    private const STANDARD_NAME_POS_Y = 110.0;
    private const STANDARD_NAME_FONT_SIZE = 45.0;
    private const STANDARD_NAME_FONT_FAMILY = 'Times';
    private const NOT_APPLICABLE = 'Not Applicable';
    private const CUSTOM_DOST_PROJECT_OPTION = 'Others';
    private const PARTICIPANTS_FILE_MAX_KB = 2048;
    private const CERTIFICATE_TEMPLATE_MAX_KB = 51200;

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
            ->where('status', CertificateEndorsement::STATUS_ENDORSED)
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
            ->where('status', CertificateEndorsement::STATUS_ENDORSED)
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

                if (!empty($names)) {
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
        return [
            $this->nationalRegularProgramLabel(),
            'LGIA (Local Grants-in-Aid Program)',
            'CEST (Community Empowerment through Science and Technology Program)',
            $this->sscpProgramLabel(),
            $this->setupProgramLabel(),
            'Others',
        ];
    }

    private function nationalRegularProgramLabel(): string
    {
        return 'National/Regular Program';
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
        return 'SSCP (Smart and Sustainable Communities Program)';
    }

    private function customDostProjectOptionLabel(): string
    {
        return 'Others, please specify';
    }

    private function setupProgramLabel(): string
    {
        return 'SETUP (Small Enterprise Technology Upgrading Program)';
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

            $generatedCertificates = $this->generateCertificatesFromPayload(
                (array) $endorsement->payload,
                $participants,
                (string) $endorsement->template_pdf_path,
                true
            );
        } catch (\Throwable $e) {
            return back()->withErrors([$e->getMessage()]);
        }

        $generated = count($generatedCertificates);
        $endorsement->update([
            'status' => CertificateEndorsement::STATUS_RD_APPROVED,
            'rd_approved_by' => $user?->id,
            'rd_approved_at' => now(),
            'rd_rejected_by' => null,
            'rd_rejected_at' => null,
            'rejection_reason' => null,
            'generated_count' => $generated,
        ]);
        $request->session()->forget($reviewKey);

        [$queuedEmails, $skippedEmails] = $this->queueGeneratedCertificateEmails($generatedCertificates);
        $message = "Regional Director approved and generated {$generated} certificates with QR and signatory block.";

        if ($queuedEmails > 0 || $skippedEmails > 0) {
            $message .= " {$queuedEmails} certificate email" . ($queuedEmails === 1 ? ' was' : 's were') . " queued";
            if ($skippedEmails > 0) {
                $message .= ", {$skippedEmails} skipped because of missing or invalid email";
            }
            $message .= '.';
        }

        $this->notifyTelegramOnRdApproval($endorsement, $user, $generated);

        return back()->with('success', $message);
    }

    public function rejectEndorsement(Request $request, int $id)
    {
        $user = $request->user();
        $this->ensureRegionalDirectorAction($user);

        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
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
        if (!config('services.telegram_bot.enabled', false)) {
            return;
        }

        $participantsCount = (int) ($endorsement->participants_count ?? 0);
        $notifyEvery = (bool) config('services.telegram_bot.notify_on_every_endorsement', true);
        $bulkThreshold = max(1, (int) config('services.telegram_bot.bulk_threshold', 50));
        if (!$notifyEvery && $participantsCount < $bulkThreshold) {
            return;
        }

        $chatIds = $this->telegramRecipientChatIds();
        if (empty($chatIds)) {
            Log::warning('Telegram endorsement notification skipped: missing Telegram chat recipients.');

            return;
        }

        $payload = is_array($endorsement->payload) ? $endorsement->payload : [];
        $trainingTitle = trim((string) ($payload['training_title'] ?? 'Untitled training'));
        $dateRange = $this->formatEndorsementDateRange($payload);
        $submittedBy = trim((string) ($submitter?->name ?? 'Unknown submitter'));
        $approvalsUrl = url('/admin/certificates/approvals');

        $message = "New certificate endorsement submitted.\n"
            . "Training: {$trainingTitle}\n"
            . "Schedule: {$dateRange}\n"
            . "Participants: {$participantsCount}\n"
            . "Submitted by: {$submittedBy}\n"
            . "Review queue: {$approvalsUrl}";

        $this->sendTelegramMessageToChats($chatIds, $message, $endorsement->id);
    }

    private function notifyTelegramOnRdApproval(
        CertificateEndorsement $endorsement,
        mixed $approver,
        int $generatedCount
    ): void {
        if (!config('services.telegram_bot.enabled', false)) {
            return;
        }

        if (!config('services.telegram_bot.notify_on_rd_approval', true)) {
            return;
        }

        $bulkThreshold = max(1, (int) config('services.telegram_bot.bulk_threshold', 50));
        $bulkOnly = (bool) config('services.telegram_bot.notify_on_rd_approval_bulk_only', true);
        if ($bulkOnly && $generatedCount < $bulkThreshold) {
            return;
        }

        $chatIds = $this->telegramRecipientChatIds();
        if (empty($chatIds)) {
            Log::warning('Telegram RD approval notification skipped: missing Telegram chat recipients.');

            return;
        }

        $payload = is_array($endorsement->payload) ? $endorsement->payload : [];
        $trainingTitle = trim((string) ($payload['training_title'] ?? 'Untitled training'));
        $dateRange = $this->formatEndorsementDateRange($payload);
        $participantsCount = (int) ($endorsement->participants_count ?? $generatedCount);
        $approvedBy = trim((string) ($approver?->name ?? 'Regional Director'));
        $queueUrl = url('/admin/certificates?status=rd_approved');

        $message = "RD approved certificate endorsement.\n"
            . "Training: {$trainingTitle}\n"
            . "Schedule: {$dateRange}\n"
            . "Participants: {$participantsCount}\n"
            . "Generated: {$generatedCount}\n"
            . "Approved by: {$approvedBy}\n"
            . "Approved queue: {$queueUrl}";

        $this->sendTelegramMessageToChats($chatIds, $message, $endorsement->id);
    }

    private function telegramRecipientChatIds(): array
    {
        $chatIds = [];
        $chatIdsCsv = (string) config('services.telegram_bot.chat_ids', '');
        if ($chatIdsCsv !== '') {
            $chatIds = array_map('trim', explode(',', $chatIdsCsv));
        }

        $legacyChatId = trim((string) config('services.telegram_bot.rd_chat_id', ''));
        if ($legacyChatId !== '') {
            foreach (explode(',', $legacyChatId) as $legacyChatIdPart) {
                $legacyChatIdPart = trim($legacyChatIdPart);
                if ($legacyChatIdPart !== '') {
                    $chatIds[] = $legacyChatIdPart;
                }
            }
        }

        $uniqueValid = [];
        foreach ($chatIds as $chatId) {
            if ($chatId === '') {
                continue;
            }

            if (!preg_match('/^(-?\d+|@[A-Za-z0-9_]{5,})$/', $chatId)) {
                continue;
            }

            $uniqueValid[$chatId] = true;
        }

        return array_keys($uniqueValid);
    }

    private function sendTelegramMessageToChats(array $chatIds, string $message, int $endorsementId): void
    {
        $botToken = trim((string) config('services.telegram_bot.bot_token', ''));
        if ($botToken === '') {
            Log::warning('Telegram notification skipped: missing bot token.', [
                'endorsement_id' => $endorsementId,
            ]);

            return;
        }

        $endpoint = "https://api.telegram.org/bot{$botToken}/sendMessage";
        foreach ($chatIds as $chatId) {
            try {
                $response = Http::asJson()
                    ->timeout(12)
                    ->post($endpoint, [
                        'chat_id' => $chatId,
                        'text' => $message,
                        'disable_web_page_preview' => true,
                    ]);

                if (!$response->successful()) {
                    Log::warning('Telegram notification failed.', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'chat_id' => $chatId,
                        'endorsement_id' => $endorsementId,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Telegram notification threw an exception.', [
                    'error' => $e->getMessage(),
                    'chat_id' => $chatId,
                    'endorsement_id' => $endorsementId,
                ]);
            }
        }
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
        $verifyUrl = $this->buildVerifyUrl((string) Str::uuid());

        try {
            $pdfContent = $this->renderStampedPdf(
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
                $this->sanitizeCaptionMarkup($request->input('caption_text')),
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
        $verifyUrl = $this->buildVerifyUrl((string) Str::uuid());

        try {
            $pdfContent = $this->renderStampedPdf(
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
        $data['caption_text'] = $this->sanitizeCaptionMarkup($data['caption_text'] ?? null);
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
        bool $applyRegionalDirectorESign = false
    ): array
    {
        $storage = Storage::disk('local');
        if (!$storage->exists($templatePath)) {
            throw new \RuntimeException('Template PDF for this request is missing in storage.');
        }
        if (empty($payload['training_title']) || empty($payload['training_date_from']) || empty($payload['issuing_office'])) {
            throw new \RuntimeException('Training details for this request are incomplete.');
        }

        $generatedCertificates = [];
        $recipientMatches = (array) ($payload['recipient_matches'] ?? []);
        foreach ($participants as $i => $participant) {
            $rowData = [
                'participant_name' => $participant['name'],
                'email' => $participant['email'] ?? null,
                'recipient_id' => $recipientMatches[$i] ?? null,
                'gender' => $participant['gender'] ?? null,
                'age' => $participant['age'] ?? null,
                'block_lot_purok' => $participant['block_lot_purok'] ?? null,
                'region' => $participant['region'] ?? null,
                'city_municipality' => $participant['city_municipality'] ?? null,
                'barangay' => $participant['barangay'] ?? null,
                'province' => $participant['province'] ?? null,
                'industry' => $participant['industry'] ?? null,
                'training_title' => $payload['training_title'] ?? '',
                'caption_text' => $payload['caption_text'] ?? null,
                'caption_alignment' => $payload['caption_alignment'] ?? 'center',
                'activity_type' => $payload['activity_type'] ?? null,
                'certificate_type' => $payload['certificate_type'] ?? null,
                'recipient_type' => $payload['recipient_type'] ?? null,
                'venue' => $payload['venue'] ?? null,
                'topic' => $payload['topic'] ?? null,
                'training_date' => $payload['training_date_from'] ?? null,
                'training_date_to' => $payload['training_date_to'] ?? null,
                'number_of_training_hours' => $payload['number_of_training_hours'] ?? null,
                'dost_program' => $payload['dost_program'] ?? null,
                'setup_office_province' => $payload['setup_office_province'] ?? self::NOT_APPLICABLE,
                'pillar' => $payload['pillar'] ?? null,
                'dost_project' => $payload['dost_project'] ?? null,
                'project_code' => $payload['project_code'] ?? null,
                'source_of_funds' => $payload['source_of_funds'] ?? self::NOT_APPLICABLE,
                'training_budget' => $payload['training_budget'] ?? null,
                'expected_number_of_participants' => $payload['expected_number_of_participants'] ?? null,
                'issuing_office' => $payload['issuing_office'] ?? '',
            ];

            $cert = $this->createCertificate($rowData);
            $sourcePath = 'certificates/source/' . $cert->certificate_code . '.pdf';
            $storage->copy($templatePath, $sourcePath);
            $this->stampCertificatePdf(
                $cert,
                $sourcePath,
                self::STANDARD_NAME_POS_X,
                self::STANDARD_NAME_POS_Y,
                self::STANDARD_NAME_FONT_SIZE,
                self::STANDARD_NAME_FONT_FAMILY,
                true,
                $applyRegionalDirectorESign,
                $payload['caption_text'] ?? null,
                $payload['caption_alignment'] ?? 'center'
            );

            // Anchor the certificate hash to Hedera (no-op unless HEDERA_ENABLED
            // and a topic is configured). Runs on the queue, never blocks issuance.
            AnchorCertificateOnHederaJob::dispatch($cert->id);

            $generatedCertificates[] = $cert->fresh();
        }

        return $generatedCertificates;
    }

    private function queueGeneratedCertificateEmails(array $certificates): array
    {
        $queued = 0;
        $skipped = 0;

        foreach ($certificates as $certificate) {
            if (! $certificate instanceof Certificate) {
                continue;
            }

            $email = trim((string) ($certificate->email ?? ''));
            if ($email === '') {
                $certificate->forceFill([
                    'email_delivery_status' => Certificate::EMAIL_STATUS_SKIPPED_NO_EMAIL,
                    'email_failure_message' => 'Certificate recipient does not have an email address.',
                    'email_queued_at' => null,
                    'email_last_attempt_at' => null,
                    'email_sent_at' => null,
                    'email_failed_at' => null,
                ])->save();
                $skipped++;
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $certificate->forceFill([
                    'email_delivery_status' => Certificate::EMAIL_STATUS_SKIPPED_INVALID_EMAIL,
                    'email_failure_message' => 'Certificate recipient email address is invalid.',
                    'email_queued_at' => null,
                    'email_last_attempt_at' => null,
                    'email_sent_at' => null,
                    'email_failed_at' => null,
                ])->save();
                $skipped++;
                continue;
            }

            $certificate->forceFill([
                'email_delivery_status' => Certificate::EMAIL_STATUS_QUEUED,
                'email_queued_at' => now(),
                'email_last_attempt_at' => null,
                'email_sent_at' => null,
                'email_failed_at' => null,
                'email_failure_message' => null,
            ])->save();

            SendCertificateEmailJob::dispatch($certificate->id);
            $queued++;
        }

        return [$queued, $skipped];
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

    private function createCertificate(array $data): Certificate
    {
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

        $program = strtoupper(trim((string) ($data['dost_program'] ?? '')));
        if (!in_array($program, $this->dostPrograms(), true)) {
            $program = '';
        }

        if ($program === '') {
            $program = $officeCodeMap[$data['issuing_office']] ?? '';
        }

        if ($program === '') {
            $program = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($data['program_code'] ?? '')));
        }
        if ($program === '') {
            $program = 'GEN';
        }

        $stopWords = ['and', 'of', 'the', 'for', 'to', 'in', 'on', 'a', 'an', '&'];
        $words = preg_split('/[^A-Za-z0-9]+/', $data['training_title']);
        $letters = '';
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            if (in_array(strtolower($word), $stopWords, true)) {
                continue;
            }
            $letters .= strtoupper($word[0]);
            if (strlen($letters) >= 3) {
                break;
            }
        }
        if ($letters === '' && !empty($words[0])) {
            $letters = strtoupper($words[0][0]);
        }
        $short = substr($letters, 0, 3);
        if ($short === '') {
            $short = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($data['training_short'] ?? '')));
            $short = substr($short, 0, 3) ?: 'GEN';
        }

        $batchNumber = Certificate::whereDate('created_at', now('Asia/Manila')->toDateString())->count() + 1;
        $batch = str_pad((string) $batchNumber, 2, '0', STR_PAD_LEFT);

        $year = date('Y', strtotime($data['training_date']));
        $prefix = "{$year}-{$program}-{$short}-{$batch}-";

        return DB::transaction(function () use ($data, $prefix) {
            $last = Certificate::where('certificate_code', 'like', $prefix . '%')
                ->orderByDesc('certificate_code')
                ->value('certificate_code');

            $nextNumber = 1;
            if ($last) {
                $tail = substr($last, -3);
                if (ctype_digit($tail)) {
                    $nextNumber = intval($tail) + 1;
                }
            }

            $code = $prefix . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
            return Certificate::create([
                'certificate_code' => $code,
                'participant_name' => $data['participant_name'],
                'email' => $data['email'] ?? null,
                'recipient_id' => $data['recipient_id'] ?? null,
                'gender' => $data['gender'] ?? null,
                'age' => $data['age'] ?? null,
                'block_lot_purok' => $data['block_lot_purok'] ?? null,
                'region' => $data['region'] ?? null,
                'city_municipality' => $data['city_municipality'] ?? null,
                'barangay' => $data['barangay'] ?? null,
                'province' => $data['province'] ?? null,
                'industry' => $data['industry'] ?? null,
                'training_title' => $data['training_title'],
                'activity_type' => $data['activity_type'] ?? null,
                'certificate_type' => $data['certificate_type'] ?? null,
                'recipient_type' => $data['recipient_type'] ?? null,
                'venue' => $data['venue'] ?? null,
                'topic' => $data['topic'] ?? null,
                'training_date' => $data['training_date'],
                'training_date_to' => $data['training_date_to'] ?? null,
                'number_of_training_hours' => $data['number_of_training_hours'] ?? null,
                'dost_program' => $data['dost_program'] ?? null,
                'setup_office_province' => $data['setup_office_province'] ?? self::NOT_APPLICABLE,
                'pillar' => $data['pillar'] ?? null,
                'dost_project' => $data['dost_project'] ?? null,
                'project_code' => $data['project_code'] ?? null,
                'source_of_funds' => $data['source_of_funds'] ?? self::NOT_APPLICABLE,
                'training_budget' => $data['training_budget'] ?? null,
                'expected_number_of_participants' => $data['expected_number_of_participants'] ?? null,
                'issuing_office' => $data['issuing_office'],
                'status' => 'valid',
            ]);
        });
    }

    private function stampCertificatePdf(
        Certificate $cert,
        string $sourcePath,
        float $namePosX,
        float $namePosY,
        float $nameFontSize,
        string $nameFontFamily,
        bool $centerName,
        bool $applyRegionalDirectorESign = false,
        ?string $captionText = null,
        string $captionAlignment = 'center'
    ): void
    {
        $verifyUrl = $this->buildVerifyUrl($cert->public_token);
        $sourceAbs = Storage::disk('local')->path($sourcePath);
        $pdfContent = $this->renderStampedPdf(
            $sourceAbs,
            $cert->participant_name,
            $namePosX,
            $namePosY,
            $nameFontSize,
            $nameFontFamily,
            $centerName,
            $cert->certificate_code,
            $verifyUrl,
            $applyRegionalDirectorESign,
            $captionText,
            $captionAlignment
        );

        $stampedRel = 'certificates/stamped/' . $cert->certificate_code . '.pdf';
        Storage::disk('local')->put($stampedRel, $pdfContent);

        $cert->update([
            'source_pdf_path' => $sourcePath,
            'stamped_pdf_path' => $stampedRel,
        ]);
    }

    private function renderStampedPdf(
        string $sourceAbs,
        string $participantName,
        float $namePosX,
        float $namePosY,
        float $nameFontSize,
        string $nameFontFamily,
        bool $centerName,
        string $codeText,
        string $verifyUrl,
        bool $applyRegionalDirectorESign = false,
        ?string $captionText = null,
        string $captionAlignment = 'center'
    ): string
    {
        $qrPng = QrCode::format('png')->size(220)->margin(1)->generate($verifyUrl);

        $tmpQr = storage_path('app/tmp_qr_' . Str::uuid() . '.png');
        @mkdir(dirname($tmpQr), 0777, true);
        file_put_contents($tmpQr, $qrPng);
        $temporaryFiles = [$tmpQr];
        $qrForPdf = PdfImageNormalizer::prepareForFpdf($tmpQr);
        if ($qrForPdf !== $tmpQr) {
            $temporaryFiles[] = $qrForPdf;
        }

        $pdf = new Fpdi();
        $converted = null;
        try {
            $pageCount = $pdf->setSourceFile($sourceAbs);
        } catch (\Throwable $e) {
            $converted = $this->convertPdfWithGhostscript($sourceAbs);
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($converted);
        }

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tplId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tplId);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId);

            if ($pageNo === 1) {
                $pdf->SetFont($nameFontFamily, '', $nameFontSize);
                $pdf->SetTextColor(0, 0, 0);
                $nameText = $this->toLatin1($participantName);
                $nameX = $namePosX;
                if ($centerName) {
                    $textWidth = $pdf->GetStringWidth($nameText);
                    $nameX = max(0, ($size['width'] - $textWidth) / 2);
                }
                $pdf->Text($nameX, $namePosY, $nameText);

                if ($captionText && trim($captionText) !== '') {
                    $this->setPdfCaptionFont($pdf, 13.2);
                    $pdf->SetTextColor(60, 60, 60);

                    $captionLines = $this->captionMarkupToStyledLines($captionText);
                    if ($captionLines !== []) {
                        $captionWidth = max(80.0, $size['width'] - 80.0);
                        $captionX = max(40.0, ($size['width'] - $captionWidth) / 2);
                        $captionY = $namePosY + 11.0;
                        $captionAlign = match ($captionAlignment) {
                            'left' => 'L',
                            'right' => 'R',
                            'justify' => 'J',
                            default => 'C',
                        };

                        $currentCaptionY = $captionY;
                        foreach ($captionLines as $lineRuns) {
                            // An empty run list marks a blank line (a double line
                            // break in the editor) and renders as a vertical gap so
                            // the PDF mirrors the editor character-for-character.
                            if ($lineRuns === []) {
                                $currentCaptionY += 5.2;
                                continue;
                            }

                            $wrappedLines = $this->buildStyledPdfLines($pdf, $lineRuns, $captionWidth, 13.2);
                            $currentCaptionY = $this->renderStyledPdfLines(
                                $pdf,
                                $wrappedLines,
                                $captionX,
                                $currentCaptionY,
                                $captionWidth,
                                5.2,
                                $captionAlign,
                                13.2
                            );
                        }
                    }
                }

                if ($applyRegionalDirectorESign) {
                    $this->stampRegionalDirectorSignatureBlock($pdf, $size);
                }
            }

            $qrSize = 20;
            $margin = 10;
            $x = $size['width'] - $qrSize - $margin;

            $textOffset = 4;
            $requiredBottom = $qrSize + $textOffset + 9;
            $maxY = $size['height'] - $margin - $requiredBottom;
            $y = min($size['height'] - $qrSize - $margin, $maxY);
            $y = max($margin, $y);

            $pdf->Image($qrForPdf, $x, $y, $qrSize, $qrSize);

            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(0, 0, 0);
            $textWidth = $pdf->GetStringWidth($codeText);
            $textX = $x + ($qrSize - $textWidth) / 2;
            $textX = max($margin, min($textX, $size['width'] - $margin - $textWidth));
            $textY = $y + $qrSize + $textOffset;
            $pdf->Text($textX, $textY, $codeText);

            $linkText = $this->toLatin1($verifyUrl);
            $pdf->SetFont('Helvetica', '', 5.5);
            $linkWidth = $pdf->GetStringWidth($linkText);
            $linkX = max($margin, $size['width'] - $margin - $linkWidth);
            $linkY = $textY + 3.5;
            $pdf->Text($linkX, $linkY, $linkText);
        }

        foreach (array_unique($temporaryFiles) as $temporaryFile) {
            @unlink($temporaryFile);
        }
        if ($converted) {
            @unlink($converted);
        }

        return $pdf->Output('S');
    }

    private function buildVerifyUrl(string $token): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $verifyPath = route('cert.verify', ['t' => $token], false);

        return $baseUrl . $verifyPath;
    }

    private function toLatin1(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value);
        if ($converted === false) {
            return utf8_decode($value);
        }

        return $converted;
    }

    private function convertPdfWithGhostscript(string $sourceAbs): string
    {
        $tmpDir = storage_path('app/tmp');
        @mkdir($tmpDir, 0777, true);
        $outPath = $tmpDir . '/fpdi_' . Str::uuid() . '.pdf';

        $cmd = 'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/prepress -dNOPAUSE -dBATCH -dSAFER -o '
            . escapeshellarg($outPath) . ' ' . escapeshellarg($sourceAbs);

        @exec($cmd, $output, $code);

        if ($code !== 0 || !is_file($outPath)) {
            $message = $code === 127
                ? 'Ghostscript (gs) is not installed on the server. Please install it to process PDFs, or upload a PDF saved as version 1.4.'
                : 'This PDF cannot be processed. Please re-save it as PDF 1.4 or contact the administrator.';
            throw new \RuntimeException($message);
        }

        return $outPath;
    }

    /**
     * Turn a stored caption into the exact lines the PDF should print.
     *
     * The result is an array of "lines", each being an ordered list of styled
     * runs (`['text' => string, 'style' => '' | 'B' | 'I' | 'BI']`). An empty
     * array marks a blank line — i.e. a double line break in the editor — which
     * the renderer turns into a vertical gap.
     *
     * Formatting is read straight from the sanitized HTML (`<strong>`, `<em>`,
     * `<br>`, `<div>`). There is no `**` / `*` markdown round-trip, so literal
     * asterisks, ampersands and quotation marks are printed verbatim and bold
     * text never leaks stray markers across wrapped lines.
     */
    private function captionMarkupToStyledLines(?string $captionText): array
    {
        $markup = $this->sanitizeCaptionMarkup($captionText);
        if ($markup === null || $markup === '') {
            return [];
        }

        // Plain captions (no formatting tags) are printed exactly as typed,
        // splitting only on real line breaks. No characters are interpreted.
        if (preg_match('/<[^>]+>/', $markup) !== 1) {
            $lines = [];
            foreach (preg_split("/\n/", $markup) ?: [] as $line) {
                $line = rtrim($line);
                $lines[] = $line === ''
                    ? []
                    : [['text' => $this->toLatin1($line), 'style' => '']];
            }

            return $this->trimBlankStyledLines($lines);
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previousState = libxml_use_internal_errors(true);
        $dom->loadHTML('<div>' . $markup . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        $root = $dom->getElementsByTagName('div')->item(0);
        if (!$root instanceof \DOMElement) {
            return [];
        }

        $state = ['lines' => [], 'current' => [], 'lastWasBreak' => true];
        foreach ($root->childNodes as $childNode) {
            $this->collectCaptionStyledRuns($childNode, false, false, $state);
        }
        if (!$state['lastWasBreak']) {
            $state['lines'][] = $state['current'];
        }

        return $this->trimBlankStyledLines($state['lines']);
    }

    /**
     * Walk a caption HTML node, appending styled runs and line breaks into the
     * shared $state accumulator.
     *
     * @param array{lines: array<int, array<int, array{text: string, style: string}>>, current: array<int, array{text: string, style: string}>, lastWasBreak: bool} $state
     */
    private function collectCaptionStyledRuns(\DOMNode $node, bool $bold, bool $italic, array &$state): void
    {
        if ($node instanceof \DOMText) {
            $text = html_entity_decode($node->nodeValue ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            // Line breaks are carried solely by <br>/<div>; any literal newline or
            // tab the markup picked up collapses to a single space, matching how
            // the editor and a browser render inline whitespace.
            $text = preg_replace('/[\r\n\t]+/', ' ', $text) ?? $text;
            if ($text === '') {
                return;
            }

            $state['current'][] = [
                'text' => $this->toLatin1($text),
                'style' => $this->captionStyleToken($bold, $italic),
            ];
            $state['lastWasBreak'] = false;

            return;
        }

        if (!$node instanceof \DOMElement) {
            return;
        }

        $tagName = strtolower($node->tagName);

        if ($tagName === 'br') {
            $state['lines'][] = $state['current'];
            $state['current'] = [];
            $state['lastWasBreak'] = true;

            return;
        }

        $childBold = $bold || in_array($tagName, ['strong', 'b'], true);
        $childItalic = $italic || in_array($tagName, ['em', 'i'], true);
        $isBlock = in_array($tagName, ['div', 'p'], true);

        // A block element starts on its own line: flush any pending inline content
        // before descending into it.
        if ($isBlock && !$state['lastWasBreak']) {
            $state['lines'][] = $state['current'];
            $state['current'] = [];
            $state['lastWasBreak'] = true;
        }

        foreach ($node->childNodes as $childNode) {
            $this->collectCaptionStyledRuns($childNode, $childBold, $childItalic, $state);
        }

        // ...and it ends the line it occupied, unless a <br> already closed it.
        if ($isBlock && !$state['lastWasBreak']) {
            $state['lines'][] = $state['current'];
            $state['current'] = [];
            $state['lastWasBreak'] = true;
        }
    }

    private function captionStyleToken(bool $bold, bool $italic): string
    {
        return ($bold ? 'B' : '') . ($italic ? 'I' : '');
    }

    /**
     * Drop blank lines from the start and end of a caption so leading/trailing
     * empty editor lines do not push the caption off-centre.
     *
     * @param array<int, array<int, array{text: string, style: string}>> $lines
     * @return array<int, array<int, array{text: string, style: string}>>
     */
    private function trimBlankStyledLines(array $lines): array
    {
        while ($lines !== [] && $lines[array_key_first($lines)] === []) {
            array_shift($lines);
        }
        while ($lines !== [] && $lines[array_key_last($lines)] === []) {
            array_pop($lines);
        }

        return array_values($lines);
    }

    private function sanitizeCaptionMarkup(?string $captionText): ?string
    {
        $captionText = trim((string) $captionText);
        if ($captionText === '') {
            return null;
        }

        $captionText = preg_replace("/\r\n?/", "\n", $captionText) ?? $captionText;
        if (preg_match('/<[^>]+>/', $captionText) !== 1) {
            return $captionText;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previousState = libxml_use_internal_errors(true);
        $dom->loadHTML('<div>' . $captionText . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        $root = $dom->getElementsByTagName('div')->item(0);
        if (!$root instanceof \DOMElement) {
            return null;
        }

        $sanitized = trim($this->sanitizeCaptionHtmlChildren($root));

        return $sanitized !== '' ? $sanitized : null;
    }

    private function sanitizeCaptionHtmlChildren(\DOMNode $node): string
    {
        $buffer = '';
        foreach ($node->childNodes as $childNode) {
            $buffer .= $this->sanitizeCaptionHtmlNode($childNode);
        }

        return $buffer;
    }

    private function sanitizeCaptionHtmlNode(\DOMNode $node): string
    {
        if ($node instanceof \DOMText) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if (!$node instanceof \DOMElement) {
            return '';
        }

        $tagName = strtolower($node->tagName);
        $content = $this->sanitizeCaptionHtmlChildren($node);

        return match ($tagName) {
            'strong', 'b' => '<strong>' . $content . '</strong>',
            'em', 'i' => '<em>' . $content . '</em>',
            'br' => '<br>',
            'div', 'p' => '<div>' . ($content !== '' ? $content : '<br>') . '</div>',
            default => $content,
        };
    }

    private function setPdfCaptionFont(Fpdi $pdf, float $size, string $style = ''): bool
    {
        $fontDir = resource_path('fonts');
        $style = strtoupper($style);
        $fontMap = [
            '' => 'Montserrat-Regular.php',
            'B' => 'Montserrat-Bold.php',
            'I' => 'Montserrat-Italic.php',
            'BI' => 'Montserrat-BoldItalic.php',
        ];
        $fontFile = $fontMap[$style] ?? $fontMap[''];
        $fontDefinition = $fontDir . DIRECTORY_SEPARATOR . $fontFile;

        if (is_file($fontDefinition)) {
            $pdf->AddFont('Montserrat', $style, $fontFile, $fontDir);
            $pdf->SetFont('Montserrat', $style, $size);
            return true;
        }

        $regularDefinition = $fontDir . DIRECTORY_SEPARATOR . 'Montserrat-Regular.php';
        if (is_file($regularDefinition)) {
            $pdf->AddFont('Montserrat', '', 'Montserrat-Regular.php', $fontDir);
            $pdf->SetFont('Montserrat', '', $size);
            return true;
        }

        $fallbackStyle = in_array($style, ['B', 'I', 'BI'], true) ? $style : '';
        $pdf->SetFont('Helvetica', $fallbackStyle, $size);
        return false;
    }

    private function hasPdfCaptionFontDefinition(string $style = ''): bool
    {
        $style = strtoupper($style);
        $fontMap = [
            '' => 'Montserrat-Regular.php',
            'B' => 'Montserrat-Bold.php',
            'I' => 'Montserrat-Italic.php',
            'BI' => 'Montserrat-BoldItalic.php',
        ];

        return is_file(resource_path('fonts/' . ($fontMap[$style] ?? $fontMap[''])));
    }

    /**
     * Wrap a single caption line (a list of styled runs) into rendered visual
     * lines that each fit within $maxWidth.
     *
     * @param array<int, array{text: string, style: string}> $runs
     */
    private function buildStyledPdfLines(Fpdi $pdf, array $runs, float $maxWidth, float $fontSize): array
    {
        $lines = [];
        $currentLine = [];
        $currentWidth = 0.0;

        foreach ($runs as $run) {
            $pieces = preg_split('/(\s+)/u', $run['text'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($pieces as $piece) {
                $isWhitespace = trim($piece) === '';
                if ($isWhitespace) {
                    if ($currentLine === [] || end($currentLine)['is_space']) {
                        continue;
                    }
                    $spaceWidth = $this->measurePdfCaptionText($pdf, ' ', $fontSize, $run['style']);
                    $currentLine[] = ['text' => ' ', 'style' => $run['style'], 'width' => $spaceWidth, 'is_space' => true];
                    $currentWidth += $spaceWidth;
                    continue;
                }

                $wordPieces = $this->splitPdfWordToWidthWithStyle($pdf, $piece, $maxWidth, $fontSize, $run['style']);
                foreach ($wordPieces as $wordPiece) {
                    $wordWidth = $this->measurePdfCaptionText($pdf, $wordPiece, $fontSize, $run['style']);

                    if ($currentLine !== [] && $currentWidth + $wordWidth > $maxWidth) {
                        while ($currentLine !== [] && end($currentLine)['is_space']) {
                            $spaceToken = array_pop($currentLine);
                            $currentWidth -= $spaceToken['width'];
                        }
                        if ($currentLine !== []) {
                            $lines[] = ['tokens' => $currentLine, 'width' => $currentWidth];
                        }
                        $currentLine = [];
                        $currentWidth = 0.0;
                    }

                    $currentLine[] = ['text' => $wordPiece, 'style' => $run['style'], 'width' => $wordWidth, 'is_space' => false];
                    $currentWidth += $wordWidth;
                }
            }
        }

        while ($currentLine !== [] && end($currentLine)['is_space']) {
            $spaceToken = array_pop($currentLine);
            $currentWidth -= $spaceToken['width'];
        }
        if ($currentLine !== []) {
            $lines[] = ['tokens' => $currentLine, 'width' => $currentWidth];
        }

        return $lines;
    }

    private function renderStyledPdfLines(
        Fpdi $pdf,
        array $lines,
        float $x,
        float $y,
        float $width,
        float $lineHeight,
        string $align,
        float $fontSize
    ): float {
        $currentY = $y;
        $lineCount = count($lines);

        foreach ($lines as $index => $line) {
            $lineWidth = (float) ($line['width'] ?? 0.0);
            $tokens = (array) ($line['tokens'] ?? []);
            $cursorX = match ($align) {
                'R' => $x + max(0.0, $width - $lineWidth),
                'C' => $x + max(0.0, ($width - $lineWidth) / 2),
                default => $x,
            };

            $spaceCount = count(array_filter($tokens, fn (array $token) => $token['is_space'] ?? false));
            $extraSpace = ($align === 'J' && $index < $lineCount - 1 && $spaceCount > 0)
                ? max(0.0, ($width - $lineWidth) / $spaceCount)
                : 0.0;

            foreach ($tokens as $token) {
                if ($token['is_space']) {
                    $cursorX += $token['width'] + $extraSpace;
                    continue;
                }

                $this->drawPdfCaptionToken($pdf, $cursorX, $currentY, $lineHeight, $token['text'], $token['style'], $fontSize);
                $cursorX += $token['width'];
            }

            $currentY += $lineHeight;
        }

        return $currentY;
    }

    private function drawPdfCaptionToken(
        Fpdi $pdf,
        float $x,
        float $y,
        float $lineHeight,
        string $text,
        string $style,
        float $fontSize
    ): void {
        $usesMontserrat = $this->setPdfCaptionFont($pdf, $fontSize, $style);
        $pdf->SetXY($x, $y);
        $pdf->Write($lineHeight, $text);

        if ($usesMontserrat && str_contains($style, 'B') && !$this->hasPdfCaptionFontDefinition($style)) {
            $boldOffsets = [
                [0.12, 0.00],
                [0.24, 0.00],
                [0.12, 0.06],
                [0.24, 0.06],
            ];

            foreach ($boldOffsets as [$offsetX, $offsetY]) {
                $pdf->SetXY($x + $offsetX, $y + $offsetY);
                $pdf->Write($lineHeight, $text);
            }
        }
    }

    private function measurePdfCaptionText(Fpdi $pdf, string $text, float $fontSize, string $style = ''): float
    {
        $this->setPdfCaptionFont($pdf, $fontSize, $style);
        return $pdf->GetStringWidth($text);
    }

    private function splitPdfWordToWidthWithStyle(Fpdi $pdf, string $word, float $maxWidth, float $fontSize, string $style = ''): array
    {
        if ($word === '' || $this->measurePdfCaptionText($pdf, $word, $fontSize, $style) <= $maxWidth) {
            return [$word];
        }

        $segments = [];
        $current = '';
        $characters = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($characters as $character) {
            $candidate = $current . $character;
            if ($current !== '' && $this->measurePdfCaptionText($pdf, $candidate, $fontSize, $style) > $maxWidth) {
                $segments[] = $current;
                $current = $character;
                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $segments[] = $current;
        }

        return $segments === [] ? [$word] : $segments;
    }


    private function stampRegionalDirectorSignatureBlock(Fpdi $pdf, array $pageSize): void
    {
        $this->stampRegionalDirectorESign($pdf, $pageSize);
    }

    private function stampRegionalDirectorESign(Fpdi $pdf, array $pageSize): ?array
    {
        $esignPath = $this->resolveRegionalDirectorESignPath();
        if (!$esignPath) {
            return null;
        }

        $boxWidth = (float) env('CERT_RD_ESIGN_WIDTH', 84);
        $boxHeight = (float) env('CERT_RD_ESIGN_HEIGHT', 28);
        if ($boxWidth <= 0 || $boxHeight <= 0) {
            return null;
        }

        [$width, $height] = $this->fitImageWithinBox($esignPath, $boxWidth, $boxHeight);

        $xEnv = env('CERT_RD_ESIGN_X');
        $yEnv = env('CERT_RD_ESIGN_Y');
        $yOffsetEnv = env('CERT_RD_ESIGN_Y_OFFSET', 0);
        $yOffset = is_numeric($yOffsetEnv) ? (float) $yOffsetEnv : 0.0;

        $x = is_numeric($xEnv)
            ? (float) $xEnv
            : (($pageSize['width'] - $boxWidth) / 2);
        $y = is_numeric($yEnv)
            ? (float) $yEnv
            : ($pageSize['height'] - $boxHeight - 18);
        $y += $yOffset;

        $margin = 5.0;
        $maxX = max($margin, $pageSize['width'] - $margin - $boxWidth);
        $maxY = max($margin, $pageSize['height'] - $margin - $boxHeight);
        $x = min(max($x, $margin), $maxX);
        $y = min(max($y, $margin), $maxY);

        $drawX = $x + (($boxWidth - $width) / 2);
        $drawY = $y + (($boxHeight - $height) / 2);

        $pdf->Image($esignPath, $drawX, $drawY, $width, $height);

        return [
            'x' => $drawX,
            'y' => $drawY,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function fitImageWithinBox(string $imagePath, float $boxWidth, float $boxHeight): array
    {
        $imageSize = @getimagesize($imagePath);
        $nativeWidth = (float) ($imageSize[0] ?? 0);
        $nativeHeight = (float) ($imageSize[1] ?? 0);

        if ($nativeWidth <= 0 || $nativeHeight <= 0) {
            return [$boxWidth, $boxHeight];
        }

        $scale = min($boxWidth / $nativeWidth, $boxHeight / $nativeHeight);
        if ($scale <= 0 || !is_finite($scale)) {
            return [$boxWidth, $boxHeight];
        }

        return [
            max(0.1, $nativeWidth * $scale),
            max(0.1, $nativeHeight * $scale),
        ];
    }

    private function resolveRegionalDirectorESignPath(): ?string
    {
        return RegionalDirectorSignatory::resolvedPath();
    }

    private function formatEndorsementDateRange(array $payload): string
    {
        $from = $payload['training_date_from'] ?? null;
        $to = $payload['training_date_to'] ?? $from;

        if (!$from) {
            return '-';
        }

        return $from === $to ? $from : ($from . ' to ' . $to);
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
        $verifyUrl = $this->buildVerifyUrl((string) Str::uuid());

        // The caption lives in the endorsement payload (see buildTrainingPayload),
        // not as a column on the endorsement row, so read it from there to match
        // what the final generated certificate prints on approval.
        $endorsementPayload = (array) $endorsement->payload;

        try {
            $pdfContent = $this->renderStampedPdf(
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
