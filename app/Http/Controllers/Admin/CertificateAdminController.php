<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendCertificateEmailJob;
use App\Models\Certificate;
use App\Models\CertificateEndorsement;
use App\Models\Setting;
use App\Models\User;
use App\Support\PdfImageNormalizer;
use App\Support\RegionalDirectorSignatory;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $endorsementRequests = (clone $endorsementBaseQuery)
            ->with(['submitter:id,name'])
            ->orderByRaw("CASE status WHEN 'endorsed' THEN 0 WHEN 'rd_rejected' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
        if ($isRegionalDirector) {
            $endorsementRequests->each(function (CertificateEndorsement $endorsement): void {
                $endorsement->setAttribute('first_participant_name', null);
                if ($endorsement->status !== CertificateEndorsement::STATUS_ENDORSED) {
                    return;
                }
                if (empty($endorsement->participants_file_path)) {
                    return;
                }

                try {
                    $participants = $this->parseParticipantStoragePath((string) $endorsement->participants_file_path);
                    $firstName = trim((string) ($participants[0]['name'] ?? ''));
                    if ($firstName !== '') {
                        $endorsement->setAttribute('first_participant_name', $firstName);
                    }
                } catch (\Throwable $e) {
                    $endorsement->setAttribute('first_participant_name', null);
                }
            });
        }

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
                'endorsementRequests',
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
            'endorsementRequests',
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
            ->limit(20)
            ->get();

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
            'pendingCount' => $pendingEndorsements->count(),
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
        ];
    }

    private function certificateTypes(): array
    {
        return [
            'Certificate of Appreciation',
            'Certificate of Participation',
            'Certificate of Recognition',
            'Certificate of Commendation',
        ];
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
        $sharedPath = $request->file('certificate_pdf_shared')->storeAs(
            'certificates/source',
            'shared_' . Str::uuid() . '.pdf',
            'local'
        );

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

        $participantsFile = $request->file('participants_file');
        $participantsExt = strtolower((string) $participantsFile->getClientOriginalExtension());
        $participantsFilePath = $participantsFile->storeAs(
            'certificate-endorsements/participants',
            'participants_' . Str::uuid() . '.' . $participantsExt,
            'local'
        );

        $templatePdfPath = $request->file('certificate_pdf_shared')->storeAs(
            'certificate-endorsements/templates',
            'template_' . Str::uuid() . '.pdf',
            'local'
        );

        CertificateEndorsement::create([
            'status' => CertificateEndorsement::STATUS_ENDORSED,
            'submitted_by' => $user?->id,
            'participants_count' => count($participants),
            'participants_file_path' => $participantsFilePath,
            'template_pdf_path' => $templatePdfPath,
            'payload' => $this->buildTrainingPayload($data),
        ]);

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

    public function preview(Request $request)
    {
        if (!$this->canPrepareCertificate($request->user())) {
            abort(403, 'You are not allowed to preview certificate requests.');
        }

        [$data, $participants] = $this->validatedCertificatePayload($request);
        $first = $participants[0] ?? null;
        if (!$first) {
            return back()->withErrors(['Please provide at least one participant.'])->withInput();
        }

        $sourceAbs = $request->file('certificate_pdf_shared')->getRealPath();

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
                true
            );
        } catch (\Throwable $e) {
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
        $automaticCertificateType = $this->automaticCertificateTypeByRecipientType()[(string) ($input['recipient_type'] ?? '')] ?? null;
        if ($automaticCertificateType !== null) {
            $input['certificate_type'] = $automaticCertificateType;
        }

        $validator = Validator::make($input, [
            'training_title' => ['required', 'string', 'max:255'],
            'activity_type' => ['required', Rule::in($this->activityTypes())],
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
            'participants_file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:2048'],
            'certificate_pdf_shared' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $data = $validator->validate();
        $automaticCertificateType = $this->automaticCertificateTypeByRecipientType()[$data['recipient_type']] ?? null;
        if ($automaticCertificateType !== null) {
            $data['certificate_type'] = $automaticCertificateType;
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
        foreach ($participants as $participant) {
            $rowData = [
                'participant_name' => $participant['name'],
                'email' => $participant['email'] ?? null,
                'gender' => $participant['gender'] ?? null,
                'age' => $participant['age'] ?? null,
                'block_lot_purok' => $participant['block_lot_purok'] ?? null,
                'region' => $participant['region'] ?? null,
                'city_municipality' => $participant['city_municipality'] ?? null,
                'barangay' => $participant['barangay'] ?? null,
                'province' => $participant['province'] ?? null,
                'industry' => $participant['industry'] ?? null,
                'training_title' => $payload['training_title'] ?? '',
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
                $applyRegionalDirectorESign
            );

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
        bool $applyRegionalDirectorESign = false
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
            $applyRegionalDirectorESign
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
        bool $applyRegionalDirectorESign = false
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

        $x = is_numeric($xEnv)
            ? (float) $xEnv
            : (($pageSize['width'] - $boxWidth) / 2);
        $y = is_numeric($yEnv)
            ? (float) $yEnv
            : ($pageSize['height'] - $boxHeight - 18);

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
                true
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
