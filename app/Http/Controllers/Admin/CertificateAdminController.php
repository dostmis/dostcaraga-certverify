<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AnchorCertificateOnHederaJob;
use App\Jobs\SendCertificateEmailJob;
use App\Models\Certificate;
use App\Models\CertificateEndorsement;
use App\Models\ParticipantIntake;
use App\Models\ParticipantIntakeEvent;
use App\Models\Recipient;
use App\Models\Setting;
use App\Models\User;
use App\Services\RecipientMatchingService;
use App\Support\PdfImageNormalizer;
use App\Support\RegionalDirectorSignatory;
use App\Support\TemplateNameBand;
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
    private const NAME_ALIGNMENTS = ['left', 'center', 'right'];
    // Long names are scaled down rather than allowed to run past the template's
    // name band, and only wrap once the smallest legible size still overflows.
    private const NAME_MIN_FONT_SIZE = 20.0;
    private const NAME_FIT_STEP_PT = 0.5;
    private const NAME_LINE_HEIGHT_RATIO = 1.15;
    // Vertical room a wrapped name may claim above its baseline. All five bundled
    // templates print "is presented to" ending at 81.3mm and the rule under the
    // name at 116.3mm, with the baseline at 110mm, so 26mm leaves the block a
    // couple of millimetres clear of the header text.
    private const NAME_MAX_ASCENT_MM = 26.0;
    // Cap height of the name face as a fraction of its point size, used to tell
    // how far the topmost line actually reaches above its own baseline.
    private const NAME_CAP_HEIGHT_RATIO = 0.66;
    // Caption font and line spacing shrink together so a long caption stays
    // inside the band between the name and the signature block.
    private const CAPTION_FONT_SIZE = 13.2;
    private const CAPTION_LINE_HEIGHT = 5.2;
    private const CAPTION_MIN_FONT_SIZE = 8.0;
    private const CAPTION_FIT_STEP_PT = 0.4;
    // Gap between the name baseline and the first caption line.
    private const CAPTION_TOP_GAP = 11.0;
    // How far the caption is inset inside the name's usable text area, each side.
    private const CAPTION_SIDE_INSET = 10.0;
    // Side margins of the usable text area, in mm. Equal by default; a design
    // with artwork down one edge overrides them per certificate.
    private const DEFAULT_NAME_MARGIN_MM = 30;
    private const MAX_NAME_MARGIN_MM = 140;
    private const MIN_NAME_BAND_MM = 60;
    // Upper bound on a whole-batch preview. Generating the PDF is cheap (the
    // template and the placeholder QR are shared across pages), but *displaying*
    // it is not: the templates carry a full-bleed 2000x1414 ICC background that a
    // viewer re-rasterises per page at roughly 0.25-0.65s, and a form XObject is
    // not cached across pages. At 500 that is minutes of rendering and well over
    // 100MB of bitmaps in the tab. A preview only has to prove the layout is
    // right, so it is capped at a couple of dozen pages and the response reports
    // the true total for the UI to show.
    private const PREVIEW_ALL_MAX_PARTICIPANTS = 25;
    // Breathing room kept between the caption and whatever sits below it.
    private const CAPTION_BOTTOM_GAP = 4.0;
    // Nudge offsets are captured in CSS pixels (1/96 inch) because that is the
    // unit the preview toolbar exposes; FPDI positions everything in mm.
    private const CSS_PIXEL_IN_MM = 25.4 / 96;
    private const POINT_IN_MM = 25.4 / 72;
    private const MAX_OFFSET_PX = 200;
    private const NOT_APPLICABLE = 'Not Applicable';
    private const CUSTOM_DOST_PROJECT_OPTION = 'Others';
    private const PARTICIPANTS_FILE_MAX_KB = 2048;
    private const CERTIFICATE_TEMPLATE_MAX_KB = 51200;

    /**
     * Request-scoped memo for the Regional Director signature lookups, which are
     * otherwise repeated for every participant of a batch.
     *
     * @var array{path?: string|null, dimensions?: array<string, array{0: float, 1: float}>}
     */
    private array $regionalDirectorESignMemo = [];

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
        $layoutOffsetLimitPx = self::MAX_OFFSET_PX;
        $nameMarginLimitMm = self::MAX_NAME_MARGIN_MM;
        $defaultNameMarginMm = self::DEFAULT_NAME_MARGIN_MM;

        $intakeEvents = ParticipantIntakeEvent::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get(['id', 'event_name', 'is_active', 'created_at']);
        if ($isRegionalDirector) {
            $intakeEvents = ParticipantIntakeEvent::query()
                ->orderByDesc('created_at')
                ->get(['id', 'event_name', 'is_active', 'user_id', 'created_at']);
        }

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
            'isRegionalDirector',
            'intakeEvents',
            'layoutOffsetLimitPx',
            'nameMarginLimitMm',
            'defaultNameMarginMm'
        ));
    }

    public function intakeEventParticipants(Request $request, int $eventId)
    {
        $user = $request->user();
        if (!$this->canPrepareCertificate($user)) {
            abort(403);
        }

        $event = ParticipantIntakeEvent::findOrFail($eventId);
        if (!$this->isRegionalDirector($user) && $event->user_id !== $user->id) {
            abort(403, 'You can only access your own intake events.');
        }

        $participants = ParticipantIntake::where('participant_intake_event_id', $eventId)
            ->where('status', 'pending')
            ->orderBy('participant_name')
            ->get(['id', 'participant_name', 'first_name', 'middle_initial', 'last_name', 'email', 'gender', 'age_range', 'region', 'province', 'city_municipality', 'barangay', 'block_lot_purok', 'industry', 'recipient_id']);

        return response()->json([
            'event_name' => $event->event_name,
            'count' => $participants->count(),
            'participants' => $participants,
        ]);
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

    private function storeParticipantsFileForEndorsement(Request $request, array $data, array $participants): string
    {
        if ($request->hasFile('participants_file')) {
            $participantsFile = $request->file('participants_file');
            $participantsExt = strtolower((string) $participantsFile->getClientOriginalExtension());
            return $participantsFile->storeAs(
                'certificate-endorsements/participants',
                'participants_' . Str::uuid() . '.' . $participantsExt,
                'local'
            );
        }

        $csvPath = 'certificate-endorsements/participants/participants_' . Str::uuid() . '.csv';
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Participant Name', 'Email', 'Gender', 'Age Range', 'Region', 'Province', 'City/Municipality', 'Barangay', 'Block/Lot/Purok', 'Industry']);
        foreach ($participants as $p) {
            fputcsv($handle, [
                $p['name'] ?? '',
                $p['email'] ?? '',
                $p['gender'] ?? '',
                $p['age_range'] ?? ($p['age'] ?? ''),
                $p['region'] ?? '',
                $p['province'] ?? '',
                $p['city_municipality'] ?? '',
                $p['barangay'] ?? '',
                $p['block_lot_purok'] ?? '',
                $p['industry'] ?? '',
            ]);
        }
        rewind($handle);
        Storage::disk('local')->put($csvPath, stream_get_contents($handle));
        fclose($handle);

        return $csvPath;
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
            ['name' => 'Others', 'code' => '', 'program_prefix' => '__ALL__', 'label' => $this->customDostProjectOptionLabel()],
        ];

        return array_map(function (array $project): array {
            if (!isset($project['program_prefix'])) {
                $project['program_prefix'] = $this->dostProjectProgramPrefix((string) ($project['code'] ?? ''));
            }

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

        if (($data['participant_source'] ?? 'file') === 'intake_link' && !empty($data['intake_event_id'])) {
            $intakeIds = array_filter(array_column($participants, 'intake_id'));
            if (!empty($intakeIds)) {
                ParticipantIntake::whereIn('id', $intakeIds)->update([
                    'status' => 'rd_approved',
                    'rd_approved_at' => now(),
                    'rd_approved_by' => $request->user()->id,
                ]);
            }
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

        $isFromIntakeLink = (($data['participant_source'] ?? 'file') === 'intake_link');

        foreach ($participants as $i => $participant) {
            if ($isFromIntakeLink && !empty($participant['recipient_id'])) {
                $matchResults[$i] = [
                    'recipient_id' => $participant['recipient_id'],
                    'confidence' => 'intake_linked',
                    'ambiguous' => false,
                    'candidates' => [],
                ];
            } else {
                $result = $matchingService->match($participant);
                $matchResults[$i] = $result;
                if ($result['recipient_id'] === null) {
                    $unresolvedCount++;
                }
            }
        }

        if ($unresolvedCount > 0) {
            $participantsFilePath = $this->storeParticipantsFileForEndorsement($request, $data, $participants);

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
            $participantsFilePath = $this->storeParticipantsFileForEndorsement($request, $data, $participants);
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

        if (($data['participant_source'] ?? 'file') === 'intake_link' && !empty($data['intake_event_id'])) {
            $intakeIds = array_filter(array_column($participants, 'intake_id'));
            if (!empty($intakeIds)) {
                ParticipantIntake::whereIn('id', $intakeIds)->update([
                    'status' => 'endorsed',
                    'endorsed_at' => now(),
                    'endorsed_by' => $user->id,
                ]);
            }
        }

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
        $layoutReport = null;
        $previewMargins = $this->normalizeNameMargins(
            $request->input('name_margin_left'),
            $request->input('name_margin_right')
        );

        try {
            $pdfContent = $this->renderStampedPdf(
                $sourceAbs,
                $participantName,
                (float) $previewMargins[0],
                self::STANDARD_NAME_POS_Y,
                self::STANDARD_NAME_FONT_SIZE,
                self::STANDARD_NAME_FONT_FAMILY,
                $this->normalizeNameAlignment($request->input('name_alignment')),
                $previewCode,
                $verifyUrl,
                true,
                $this->sanitizeCaptionMarkup($request->input('caption_text')),
                (string) $request->input('caption_alignment', 'center'),
                $this->normalizeQrLabelFlag($request->input('qr_show_code')),
                $this->normalizeQrLabelFlag($request->input('qr_show_link')),
                nameOffsetPx: $this->normalizeOffsetPx($request->input('name_offset_x')),
                nameOffsetYPx: $this->normalizeOffsetPx($request->input('name_offset_y')),
                signatureOffsetPx: $this->normalizeOffsetPx($request->input('signature_offset_x')),
                layoutReport: $layoutReport,
                nameMarginRight: $previewMargins[1]
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // The preview is returned as raw PDF bytes, so the fitting result rides
        // along in headers for the editor to turn into an on-screen warning.
        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificate-live-preview.pdf"',
            'X-Caption-Overflow' => ($layoutReport['caption']['overflows'] ?? false) ? '1' : '0',
            'X-Caption-Lines' => (string) ($layoutReport['caption']['lines'] ?? 0),
            'X-Caption-Max-Lines' => (string) ($layoutReport['caption']['max_lines'] ?? 0),
            'X-Caption-Font-Size' => (string) round((float) ($layoutReport['caption']['font_size'] ?? 0), 1),
            'X-Name-Font-Size' => (string) round((float) ($layoutReport['name']['font_size'] ?? 0), 1),
            'X-Name-Lines' => (string) ($layoutReport['name']['lines'] ?? 0),
            // Distinguishes "no signatory configured" (fine) from "configured but
            // the image is missing" (the operator needs to know before printing).
            'X-Signature-Expected' => RegionalDirectorSignatory::enabled() ? '1' : '0',
            'X-Signature-Stamped' => ($layoutReport['signature_stamped'] ?? false) ? '1' : '0',
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
                (float) ($data['name_margin_left'] ?? self::DEFAULT_NAME_MARGIN_MM),
                self::STANDARD_NAME_POS_Y,
                self::STANDARD_NAME_FONT_SIZE,
                self::STANDARD_NAME_FONT_FAMILY,
                $data['name_alignment'] ?? 'center',
                $previewCode,
                $verifyUrl,
                true,
                $data['caption_text'] ?? null,
                $data['caption_alignment'] ?? 'center',
                $data['qr_show_code'] ?? true,
                $data['qr_show_link'] ?? true,
                nameOffsetPx: $data['name_offset_x'] ?? 0,
                nameOffsetYPx: $data['name_offset_y'] ?? 0,
                signatureOffsetPx: $data['signature_offset_x'] ?? 0,
                nameMarginRight: (float) ($data['name_margin_right'] ?? self::DEFAULT_NAME_MARGIN_MM)
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

    /**
     * Preview every participant's certificate in one document, so whoever is
     * preparing or endorsing a batch can page through the whole thing before it
     * is generated rather than trusting a single sample.
     */
    public function previewAll(Request $request)
    {
        if (!$this->canPrepareCertificate($request->user())) {
            abort(403, 'You are not allowed to preview certificate requests.');
        }

        [$data, $participants] = $this->validatedCertificatePayload($request);

        $names = $this->previewParticipantNames($participants);
        if ($names === []) {
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

        $total = count($names);
        $names = array_slice($names, 0, self::PREVIEW_ALL_MAX_PARTICIPANTS);
        $layoutReport = null;

        try {
            $pdfContent = $this->renderStampedPdfForParticipants(
                $sourceAbs,
                $this->buildPreviewParticipants($names),
                (float) ($data['name_margin_left'] ?? self::DEFAULT_NAME_MARGIN_MM),
                self::STANDARD_NAME_POS_Y,
                self::STANDARD_NAME_FONT_SIZE,
                self::STANDARD_NAME_FONT_FAMILY,
                $data['name_alignment'] ?? 'center',
                true,
                $data['caption_text'] ?? null,
                $data['caption_alignment'] ?? 'center',
                $data['qr_show_code'] ?? true,
                $data['qr_show_link'] ?? true,
                $data['name_offset_x'] ?? 0,
                $data['signature_offset_x'] ?? 0,
                $layoutReport,
                (float) ($data['name_margin_right'] ?? self::DEFAULT_NAME_MARGIN_MM),
                $data['name_offset_y'] ?? 0
            );
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors([$e->getMessage()])->withInput();
        }

        return response($pdfContent, 200, $this->previewAllHeaders(
            'certificate-preview-all-participants.pdf',
            count($names),
            $total,
            $layoutReport
        ));
    }

    /**
     * Preview every participant of a submitted endorsement, for whoever is
     * reviewing it before approval.
     */
    private function previewAllHeaders(string $filename, int $shown, int $total, ?array $layoutReport): array
    {
        $shrunk = 0;
        $overflowing = 0;
        foreach (($layoutReport['participants'] ?? []) as $row) {
            if ($row['name_fit']['shrunk'] ?? false) {
                $shrunk++;
            }
            if ($row['caption']['overflows'] ?? false) {
                $overflowing++;
            }
        }

        // The preview opens as a raw PDF in a new tab, so no page of ours runs to
        // read the counters below. The filename is the one label the viewer does
        // put on screen, so a truncated batch says so there rather than quietly
        // looking like the whole list.
        if ($shown < $total) {
            $filename = str_replace(
                '-all-participants',
                '-first-' . $shown . '-of-' . $total . '-participants',
                $filename
            );
        }

        return [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            // Read by the preparer's browser tab so the counts can be shown
            // without re-parsing the PDF.
            'X-Preview-Participants' => (string) $shown,
            'X-Preview-Total-Participants' => (string) $total,
            'X-Preview-Truncated' => $shown < $total ? '1' : '0',
            'X-Preview-Names-Shrunk' => (string) $shrunk,
            'X-Preview-Captions-Overflowing' => (string) $overflowing,
        ];
    }

    /**
     * Preview pages share one placeholder verification URL: the tokens are not
     * real, and reusing them means the QR image is rendered once for the whole
     * batch instead of once per participant.
     *
     * @param  list<string>  $names
     * @return list<array{name: string, code: string, verify_url: string}>
     */
    private function buildPreviewParticipants(array $names): array
    {
        $verifyUrl = $this->buildVerifyUrl((string) Str::uuid());

        return array_map(fn (string $name) => [
            'name' => $name,
            'code' => 'PREVIEW-' . strtoupper(Str::random(6)),
            'verify_url' => $verifyUrl,
        ], $names);
    }

    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @return list<string>
     */
    private function previewParticipantNames(array $participants): array
    {
        $names = [];
        foreach ($participants as $participant) {
            $name = trim((string) ($participant['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function validatedCertificatePayload(Request $request): array
    {
        $input = $request->all();
        if (empty($input['template_source'])) {
            $input['template_source'] = $request->hasFile('certificate_pdf_shared') ? 'custom' : 'default';
        }
        if (empty($input['participant_source'])) {
            $input['participant_source'] = $request->hasFile('participants_file') ? 'file' : 'intake_link';
        }
        $automaticCertificateType = $this->automaticCertificateTypeByRecipientType()[(string) ($input['recipient_type'] ?? '')] ?? null;
        if ($automaticCertificateType !== null) {
            $input['certificate_type'] = $automaticCertificateType;
        }

        $participantsFileRules = ($input['participant_source'] === 'file')
            ? ['required', 'file', 'mimes:csv,txt,xlsx', 'max:' . self::PARTICIPANTS_FILE_MAX_KB]
            : ['nullable', 'file', 'mimes:csv,txt,xlsx', 'max:' . self::PARTICIPANTS_FILE_MAX_KB];

        $validator = Validator::make($input, [
            'training_title' => ['required', 'string', 'max:255'],
            'participant_source' => ['required', 'in:intake_link,file'],
            'intake_event_id' => ['required_if:participant_source,intake_link', 'nullable', 'integer', 'exists:participant_intake_events,id'],
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
            'participants_file' => $participantsFileRules,
            'template_source' => ['required', 'in:default,custom'],
            'certificate_pdf_shared' => ['required_if:template_source,custom', 'file', 'mimes:pdf', 'max:' . self::CERTIFICATE_TEMPLATE_MAX_KB],
            'caption_text' => ['nullable', 'string'],
            'caption_alignment' => ['nullable', 'string', 'in:left,center,right,justify'],
            'name_alignment' => ['nullable', 'string', Rule::in(self::NAME_ALIGNMENTS)],
            'qr_show_code' => ['nullable', 'boolean'],
            'qr_show_link' => ['nullable', 'boolean'],
            'name_margin_left' => ['nullable', 'integer', 'between:0,' . self::MAX_NAME_MARGIN_MM],
            'name_margin_right' => ['nullable', 'integer', 'between:0,' . self::MAX_NAME_MARGIN_MM],
            'name_offset_x' => ['nullable', 'integer', 'between:-' . self::MAX_OFFSET_PX . ',' . self::MAX_OFFSET_PX],
            'name_offset_y' => ['nullable', 'integer', 'between:-' . self::MAX_OFFSET_PX . ',' . self::MAX_OFFSET_PX],
            'signature_offset_x' => ['nullable', 'integer', 'between:-' . self::MAX_OFFSET_PX . ',' . self::MAX_OFFSET_PX],
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
        $data['name_alignment'] = $this->normalizeNameAlignment($data['name_alignment'] ?? null);
        $data['qr_show_code'] = $this->normalizeQrLabelFlag($data['qr_show_code'] ?? null);
        $data['qr_show_link'] = $this->normalizeQrLabelFlag($data['qr_show_link'] ?? null);
        $data['name_offset_x'] = $this->normalizeOffsetPx($data['name_offset_x'] ?? null);
        $data['name_offset_y'] = $this->normalizeOffsetPx($data['name_offset_y'] ?? null);
        $data['signature_offset_x'] = $this->normalizeOffsetPx($data['signature_offset_x'] ?? null);
        [$data['name_margin_left'], $data['name_margin_right']] = $this->normalizeNameMargins(
            $data['name_margin_left'] ?? null,
            $data['name_margin_right'] ?? null
        );
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
            'name_alignment' => $data['name_alignment'] ?? 'center',
            'qr_show_code' => $data['qr_show_code'] ?? true,
            'qr_show_link' => $data['qr_show_link'] ?? true,
            'name_margin_left' => $data['name_margin_left'] ?? self::DEFAULT_NAME_MARGIN_MM,
            'name_margin_right' => $data['name_margin_right'] ?? self::DEFAULT_NAME_MARGIN_MM,
            'name_offset_x' => $data['name_offset_x'] ?? 0,
            'name_offset_y' => $data['name_offset_y'] ?? 0,
            'signature_offset_x' => $data['signature_offset_x'] ?? 0,
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
                'name_alignment' => $this->normalizeNameAlignment($payload['name_alignment'] ?? null),
                'qr_show_code' => $this->normalizeQrLabelFlag($payload['qr_show_code'] ?? null),
                'qr_show_link' => $this->normalizeQrLabelFlag($payload['qr_show_link'] ?? null),
                'name_margin_left' => $this->normalizeMarginMm($payload['name_margin_left'] ?? null),
                'name_margin_right' => $this->normalizeMarginMm($payload['name_margin_right'] ?? null),
                'name_offset_x' => $this->normalizeOffsetPx($payload['name_offset_x'] ?? null),
                'name_offset_y' => $this->normalizeOffsetPx($payload['name_offset_y'] ?? null),
                'signature_offset_x' => $this->normalizeOffsetPx($payload['signature_offset_x'] ?? null),
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
                (float) $cert->name_margin_left,
                self::STANDARD_NAME_POS_Y,
                self::STANDARD_NAME_FONT_SIZE,
                self::STANDARD_NAME_FONT_FAMILY,
                (string) $cert->name_alignment,
                $applyRegionalDirectorESign,
                $payload['caption_text'] ?? null,
                $payload['caption_alignment'] ?? 'center',
                (bool) $cert->qr_show_code,
                (bool) $cert->qr_show_link,
                nameOffsetPx: (int) $cert->name_offset_x,
                nameOffsetYPx: (int) $cert->name_offset_y,
                signatureOffsetPx: (int) $cert->signature_offset_x,
                nameMarginRight: (float) $cert->name_margin_right
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
        if (($data['participant_source'] ?? 'file') === 'intake_link' && !empty($data['intake_event_id'])) {
            return $this->resolveParticipantsFromIntakeEvent((int) $data['intake_event_id'], $request->user());
        }

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

    private function resolveParticipantsFromIntakeEvent(int $eventId, ?User $user): array
    {
        $event = ParticipantIntakeEvent::find($eventId);
        if (!$event) {
            return [];
        }

        if (!$this->isRegionalDirector($user) && $event->user_id !== $user?->id) {
            return [];
        }

        $intakes = ParticipantIntake::where('participant_intake_event_id', $eventId)
            ->where('status', 'pending')
            ->orderBy('participant_name')
            ->get();

        $rows = [];
        foreach ($intakes as $intake) {
            $rows[] = [
                'name' => $intake->participant_name,
                'email' => $intake->email ?: null,
                'gender' => $intake->gender ?: null,
                'age' => null,
                'age_range' => $intake->age_range ?: null,
                'block_lot_purok' => $intake->block_lot_purok ?: null,
                'region' => $intake->region ?: null,
                'city_municipality' => $intake->city_municipality ?: null,
                'barangay' => $intake->barangay ?: null,
                'province' => $intake->province ?: null,
                'industry' => $intake->industry ?: null,
                'intake_id' => $intake->id,
                'recipient_id' => $intake->recipient_id,
            ];
        }

        return $rows;
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
                'name_alignment' => $this->normalizeNameAlignment($data['name_alignment'] ?? null),
                'qr_show_code' => $this->normalizeQrLabelFlag($data['qr_show_code'] ?? null),
                'qr_show_link' => $this->normalizeQrLabelFlag($data['qr_show_link'] ?? null),
                'name_margin_left' => $this->normalizeMarginMm($data['name_margin_left'] ?? null),
                'name_margin_right' => $this->normalizeMarginMm($data['name_margin_right'] ?? null),
                'name_offset_x' => $this->normalizeOffsetPx($data['name_offset_x'] ?? null),
                'name_offset_y' => $this->normalizeOffsetPx($data['name_offset_y'] ?? null),
                'signature_offset_x' => $this->normalizeOffsetPx($data['signature_offset_x'] ?? null),
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
        string $nameAlignment,
        bool $applyRegionalDirectorESign = false,
        ?string $captionText = null,
        string $captionAlignment = 'center',
        bool $showQrCode = true,
        bool $showQrLink = true,
        int $nameOffsetPx = 0,
        int $signatureOffsetPx = 0,
        ?float $nameMarginRight = null,
        int $nameOffsetYPx = 0
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
            $nameAlignment,
            $cert->certificate_code,
            $verifyUrl,
            $applyRegionalDirectorESign,
            $captionText,
            $captionAlignment,
            $showQrCode,
            $showQrLink,
            nameOffsetPx: $nameOffsetPx,
            nameOffsetYPx: $nameOffsetYPx,
            signatureOffsetPx: $signatureOffsetPx,
            nameMarginRight: $nameMarginRight
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
        string $nameAlignment,
        string $codeText,
        string $verifyUrl,
        bool $applyRegionalDirectorESign = false,
        ?string $captionText = null,
        string $captionAlignment = 'center',
        bool $showQrCode = true,
        bool $showQrLink = true,
        int $nameOffsetPx = 0,
        int $signatureOffsetPx = 0,
        ?array &$layoutReport = null,
        ?float $nameMarginRight = null,
        int $nameOffsetYPx = 0
    ): string
    {
        return $this->renderStampedPdfForParticipants(
            $sourceAbs,
            [['name' => $participantName, 'code' => $codeText, 'verify_url' => $verifyUrl]],
            $namePosX,
            $namePosY,
            $nameFontSize,
            $nameFontFamily,
            $nameAlignment,
            $applyRegionalDirectorESign,
            $captionText,
            $captionAlignment,
            $showQrCode,
            $showQrLink,
            $nameOffsetPx,
            $signatureOffsetPx,
            $layoutReport,
            $nameMarginRight,
            $nameOffsetYPx
        );
    }

    /**
     * Stamp one or more participants into a single document: the template pages
     * are imported once and repeated per participant, which is what makes a
     * whole-batch preview affordable to render in one request.
     *
     * @param  list<array{name: string, code: string, verify_url: string}>  $participants
     */
    private function renderStampedPdfForParticipants(
        string $sourceAbs,
        array $participants,
        float $namePosX,
        float $namePosY,
        float $nameFontSize,
        string $nameFontFamily,
        string $nameAlignment,
        bool $applyRegionalDirectorESign = false,
        ?string $captionText = null,
        string $captionAlignment = 'center',
        bool $showQrCode = true,
        bool $showQrLink = true,
        int $nameOffsetPx = 0,
        int $signatureOffsetPx = 0,
        ?array &$layoutReport = null,
        ?float $nameMarginRight = null,
        int $nameOffsetYPx = 0
    ): string
    {
        // Populated as the pages are stamped so callers (the live preview, batch
        // generation) can warn when the text did not fit the space available.
        // The top-level entries describe the first participant so single-name
        // callers keep the shape they already read.
        $layoutReport = [
            'caption' => null,
            'name' => null,
            'signature_stamped' => false,
            'participants' => [],
        ];

        $temporaryFiles = [];
        $qrImages = [];

        $pdf = new Fpdi();
        // Every page is added explicitly from the template, so FPDF's automatic
        // page break must stay off: without it, an overlong caption spills into
        // dozens of blank pages instead of being fitted to the page it belongs on.
        $pdf->SetAutoPageBreak(false);
        $converted = null;
        try {
            $pageCount = $pdf->setSourceFile($sourceAbs);
        } catch (\Throwable $e) {
            $converted = $this->convertPdfWithGhostscript($sourceAbs);
            $pdf = new Fpdi();
            $pdf->SetAutoPageBreak(false);
            $pageCount = $pdf->setSourceFile($converted);
        }

        // Import each template page once and reuse it for every participant, so
        // a 200-name batch embeds the artwork once rather than 200 times.
        $templates = [];
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tplId = $pdf->importPage($pageNo);
            $templates[$pageNo] = ['id' => $tplId, 'size' => $pdf->getTemplateSize($tplId)];
        }

        // Where this template already has artwork, so the name can be laid out
        // around it instead of on top of it. Mapped once per template and
        // cached, not per participant.
        $firstPage = $templates[1]['size'] ?? ['width' => 297.0, 'height' => 210.0];
        $templateInkMap = TemplateNameBand::inkMap(
            $converted ?? $sourceAbs,
            (float) $firstPage['width'],
            (float) $firstPage['height']
        );

        foreach (array_values($participants) as $participantIndex => $participant) {
            $participantName = (string) ($participant['name'] ?? '');
            $codeText = (string) ($participant['code'] ?? '');
            $verifyUrl = (string) ($participant['verify_url'] ?? '');

            // Rendering a QR costs far more than the rest of the page put
            // together, so identical verification URLs share one image. Issued
            // certificates each have their own URL and so are unaffected; a
            // batch preview passes one placeholder URL and pays for it once.
            if (!array_key_exists($verifyUrl, $qrImages)) {
                $qrPng = QrCode::format('png')->size(220)->margin(1)->generate($verifyUrl);
                $tmpQr = storage_path('app/tmp_qr_' . Str::uuid() . '.png');
                @mkdir(dirname($tmpQr), 0777, true);
                file_put_contents($tmpQr, $qrPng);
                $temporaryFiles[] = $tmpQr;
                $normalizedQr = PdfImageNormalizer::prepareForFpdf($tmpQr);
                if ($normalizedQr !== $tmpQr) {
                    $temporaryFiles[] = $normalizedQr;
                }
                $qrImages[$verifyUrl] = $normalizedQr;
            }
            $qrForPdf = $qrImages[$verifyUrl];

            $participantReport = ['name' => $participantName, 'caption' => null, 'name_fit' => null];

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tplId = $templates[$pageNo]['id'];
                $size = $templates[$pageNo]['size'];
                $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($tplId);

                if ($pageNo === 1) {
                    $pdf->SetTextColor(0, 0, 0);
                    $nameText = $this->toLatin1($participantName);
                    // $namePosX is the left margin of the usable text area and
                    // $nameMarginRight the right one. They are equal by default, so
                    // 'center' resolves to the page centre exactly as before; a
                    // design with artwork down one edge sets them asymmetrically and
                    // the name then centres on the clear space instead of the page.
                    $nameMarginLeft = max(0.0, $namePosX);
                    $nameMarginRight = max(0.0, $nameMarginRight ?? $namePosX);
                    $nameBandWidth = max(10.0, $size['width'] - $nameMarginLeft - $nameMarginRight);
                    // A name wider than the band used to be drawn at a negative X and
                    // clipped off both edges, so it is fitted to the band first.
                    // The operator's vertical nudge moves the baseline, so the
                    // room above it changes with them: nudging the name down
                    // buys back space for a second line, nudging it up spends it.
                    $nameNudgeY = $this->offsetPxToMm($nameOffsetYPx);
                    [$nameSize, $nameLines] = $this->fitNameToBand(
                        $pdf,
                        $nameText,
                        $nameFontFamily,
                        $nameFontSize,
                        $nameBandWidth,
                        $templateInkMap,
                        $namePosY + $nameNudgeY,
                        $nameAlignment,
                        $nameMarginLeft
                    );

                    $participantReport['name_fit'] = [
                        'font_size' => $nameSize,
                        'nominal_font_size' => $nameFontSize,
                        'lines' => count($nameLines),
                        'shrunk' => $nameSize < $nameFontSize,
                    ];
                    if ($participantIndex === 0) {
                        $layoutReport['name'] = $participantReport['name_fit'];
                    }

                    $pdf->SetFont($nameFontFamily, '', $nameSize);
                    $nameLineHeight = $nameSize * self::POINT_IN_MM * self::NAME_LINE_HEIGHT_RATIO;
                    // A wrapped name grows upward, keeping its last line on the
                    // original baseline. Centring the block instead pushed the
                    // second line down through the rule the templates print under
                    // the name, and left the caption less room besides.
                    $nameBaselineY = $namePosY + $nameNudgeY - ((count($nameLines) - 1) * $nameLineHeight);
                    $nameNudge = $this->offsetPxToMm($nameOffsetPx);

                    foreach ($nameLines as $nameLineIndex => $nameLine) {
                        $nameWidth = $pdf->GetStringWidth($nameLine);
                        $nameX = match ($nameAlignment) {
                            'left' => $nameMarginLeft,
                            'right' => max(0.0, $size['width'] - $nameMarginRight - $nameWidth),
                            // Centred within the usable area, which equals the page
                            // centre whenever the two margins match.
                            default => max(0.0, $nameMarginLeft + (($nameBandWidth - $nameWidth) / 2)),
                        };
                        // The nudge is a fine adjustment layered on top of the chosen
                        // alignment, so operators can align against template artwork.
                        $nameX += $nameNudge;
                        $pdf->Text($nameX, $nameBaselineY + ($nameLineIndex * $nameLineHeight), $nameLine);
                    }

                    $captionTopY = $namePosY + self::CAPTION_TOP_GAP;

                    if ($captionText && trim($captionText) !== '') {
                        $captionLines = $this->captionMarkupToStyledLines($captionText);
                        if ($captionLines !== []) {
                            // The caption sits inside the same usable text area as the
                            // name, inset a further 10mm each side. With the default
                            // 30mm margins that reproduces the previous 40mm inset,
                            // and on an asymmetric design it keeps the caption off the
                            // artwork too.
                            $captionLeft = $nameMarginLeft + self::CAPTION_SIDE_INSET;
                            $captionRight = $nameMarginRight + self::CAPTION_SIDE_INSET;
                            $captionWidth = max(80.0, $size['width'] - $captionLeft - $captionRight);
                            $captionX = max(0.0, $captionLeft);
                            $captionAlign = match ($captionAlignment) {
                                'left' => 'L',
                                'right' => 'R',
                                'justify' => 'J',
                                default => 'C',
                            };

                            // The caption may only use the band between the name and
                            // whatever sits below it, so it is scaled to that height
                            // instead of running over the signature or off the page.
                            $captionMaxHeight = max(
                                0.0,
                                $this->captionBottomBound($size, $applyRegionalDirectorESign) - $captionTopY
                            );
                            [$captionFontSize, $captionLineHeight, $captionParagraphs, $captionFit] = $this->fitCaptionToBox(
                                $pdf,
                                $captionLines,
                                $captionWidth,
                                $captionMaxHeight
                            );
                            $participantReport['caption'] = $captionFit + ['max_height' => $captionMaxHeight];
                            if ($participantIndex === 0) {
                                $layoutReport['caption'] = $participantReport['caption'];
                            }

                            $pdf->SetTextColor(60, 60, 60);
                            $currentCaptionY = $captionTopY;
                            foreach ($captionParagraphs as $wrappedLines) {
                                // An empty line list marks a blank line (a double line
                                // break in the editor) and renders as a vertical gap so
                                // the PDF mirrors the editor character-for-character.
                                if ($wrappedLines === []) {
                                    $currentCaptionY += $captionLineHeight;
                                    continue;
                                }

                                $currentCaptionY = $this->renderStyledPdfLines(
                                    $pdf,
                                    $wrappedLines,
                                    $captionX,
                                    $currentCaptionY,
                                    $captionWidth,
                                    $captionLineHeight,
                                    $captionAlign,
                                    $captionFontSize
                                );
                            }
                        }
                    }

                    if ($applyRegionalDirectorESign) {
                        $stamped = $this->stampRegionalDirectorSignatureBlock($pdf, $size, $signatureOffsetPx);
                        if ($participantIndex === 0) {
                            $layoutReport['signature_stamped'] = $stamped;
                        }

                        if (!$stamped && $participantIndex === 0) {
                            // The signature silently vanishing is indistinguishable
                            // from a template that never had one, so it is logged
                            // rather than left for someone to notice on paper.
                            Log::warning('Regional Director e-signature was requested but could not be stamped.', [
                                'configured_path' => RegionalDirectorSignatory::configuredPath(),
                                'enabled' => RegionalDirectorSignatory::enabled(),
                            ]);
                        }
                    }
                }

                $qrSize = 20;
                $margin = 10;
                $x = $size['width'] - $qrSize - $margin;

                $linkText = $this->toLatin1($verifyUrl);
                $drawCode = $showQrCode && trim($codeText) !== '';
                $drawLink = $showQrLink && trim($linkText) !== '';

                $textOffset = 4;
                // Only reserve the space the captions under the QR actually need, so
                // hiding both lets the QR sit flush in the bottom-right corner.
                $labelReserve = match (true) {
                    $drawCode && $drawLink => $textOffset + 9,
                    $drawCode || $drawLink => $textOffset + 4,
                    default => 0,
                };
                $requiredBottom = $qrSize + $labelReserve;
                $maxY = $size['height'] - $margin - $requiredBottom;
                $y = min($size['height'] - $qrSize - $margin, $maxY);
                $y = max($margin, $y);

                $pdf->Image($qrForPdf, $x, $y, $qrSize, $qrSize);

                $labelY = $y + $qrSize + $textOffset;

                if ($drawCode) {
                    $pdf->SetFont('Helvetica', '', 8);
                    $pdf->SetTextColor(0, 0, 0);
                    $textWidth = $pdf->GetStringWidth($codeText);
                    $textX = $x + ($qrSize - $textWidth) / 2;
                    $textX = max($margin, min($textX, $size['width'] - $margin - $textWidth));
                    $pdf->Text($textX, $labelY, $codeText);
                    $labelY += 3.5;
                }

                if ($drawLink) {
                    $pdf->SetFont('Helvetica', '', 5.5);
                    $pdf->SetTextColor(0, 0, 0);
                    $linkWidth = $pdf->GetStringWidth($linkText);
                    $linkX = max($margin, $size['width'] - $margin - $linkWidth);
                    $pdf->Text($linkX, $labelY, $linkText);
                }
            }

            $layoutReport['participants'][] = $participantReport;
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

    /**
     * Participant-name alignment is a presentation choice made in the live
     * preview; anything unrecognised falls back to the historic centred layout.
     */
    private function normalizeNameAlignment(mixed $value): string
    {
        $alignment = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($alignment, self::NAME_ALIGNMENTS, true) ? $alignment : 'center';
    }

    /**
     * The QR caption toggles default to enabled so certificates prepared before
     * the toggles existed keep printing their code and verification link.
     */
    private function normalizeQrLabelFlag(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Horizontal nudge in CSS pixels, clamped so a stray value can never push
     * the name or signature off the page.
     */
    private function normalizeOffsetPx(mixed $value): int
    {
        if (!is_numeric($value)) {
            return 0;
        }

        return max(-self::MAX_OFFSET_PX, min(self::MAX_OFFSET_PX, (int) round((float) $value)));
    }

    /**
     * A blank or unusable margin falls back to the standard symmetric band, so
     * a certificate never ends up with no room to print the name in.
     */
    private function normalizeMarginMm(mixed $value): int
    {
        if (!is_numeric($value)) {
            return self::DEFAULT_NAME_MARGIN_MM;
        }

        return max(0, min(self::MAX_NAME_MARGIN_MM, (int) round((float) $value)));
    }

    /**
     * Normalise the pair together: margins that would leave too little room to
     * print a name in fall back to the standard symmetric band rather than
     * producing a certificate with a sliver of usable width.
     *
     * @return array{0: int, 1: int}
     */
    private function normalizeNameMargins(mixed $left, mixed $right): array
    {
        $leftMm = $this->normalizeMarginMm($left);
        $rightMm = $this->normalizeMarginMm($right);

        // A4 landscape is the narrowest page these templates use; checking
        // against it keeps the guard independent of the template being stamped.
        $narrowestPageMm = 297.0;
        if (($narrowestPageMm - $leftMm - $rightMm) < self::MIN_NAME_BAND_MM) {
            return [self::DEFAULT_NAME_MARGIN_MM, self::DEFAULT_NAME_MARGIN_MM];
        }

        return [$leftMm, $rightMm];
    }

    private function offsetPxToMm(int $offsetPx): float
    {
        return $offsetPx * self::CSS_PIXEL_IN_MM;
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
     * Scale the participant name down until it fits the template's name band,
     * wrapping onto extra lines only when even the smallest allowed size still
     * overflows. A name that already fits keeps the requested size untouched.
     *
     * @return array{0: float, 1: list<string>}
     */
    private function fitNameToBand(
        Fpdi $pdf,
        string $nameText,
        string $fontFamily,
        float $nominalSize,
        float $maxWidth,
        ?array $inkMap = null,
        float $baselineY = 0.0,
        string $alignment = 'center',
        float $marginLeft = 0.0
    ): array {
        $pdf->SetFont($fontFamily, '', $nominalSize);
        if (trim($nameText) === '' || $pdf->GetStringWidth($nameText) <= $maxWidth) {
            return [$nominalSize, [$nameText]];
        }

        $minSize = min($nominalSize, self::NAME_MIN_FONT_SIZE);

        // A name too wide for one line is wrapped at as close to the nominal
        // size as the template allows, rather than being shrunk onto a single
        // small line. Each candidate layout is checked against the artwork it
        // would actually sit on, so a heading above the name pushes the size
        // down while a decorative border beside it does not.
        for ($size = $nominalSize; $size >= $minSize; $size -= self::NAME_FIT_STEP_PT) {
            $pdf->SetFont($fontFamily, '', $size);
            $lines = $this->balanceNameLines(
                $pdf,
                $nameText,
                $this->wrapNameToWidth($pdf, $nameText, $maxWidth),
                $maxWidth
            );

            if ($this->nameLayoutClearsArtwork($pdf, $lines, $size, $inkMap, $baselineY, $alignment, $marginLeft, $maxWidth)) {
                return [$size, $lines];
            }
        }

        // Nothing cleared the artwork — a template whose name area is covered by
        // a background image, say. Fall back to the geometric budget so the name
        // still prints at a reasonable size instead of dropping to the minimum.
        for ($size = $nominalSize; $size >= $minSize; $size -= self::NAME_FIT_STEP_PT) {
            $pdf->SetFont($fontFamily, '', $size);
            $lines = $this->wrapNameToWidth($pdf, $nameText, $maxWidth);
            if ($this->nameBlockAscent($size, count($lines)) <= self::NAME_MAX_ASCENT_MM) {
                return [$size, $this->balanceNameLines($pdf, $nameText, $lines, $maxWidth)];
            }
        }

        $pdf->SetFont($fontFamily, '', $minSize);

        return [$minSize, $this->wrapNameToWidth($pdf, $nameText, $maxWidth)];
    }

    /**
     * Would this candidate layout land on artwork? Every line is boxed at the
     * position it would be drawn and tested against the template's ink map.
     *
     * @param  list<string>  $lines
     * @param  array{cols: int, rows: int, grid: list<string>}|null  $inkMap
     */
    private function nameLayoutClearsArtwork(
        Fpdi $pdf,
        array $lines,
        float $size,
        ?array $inkMap,
        float $baselineY,
        string $alignment,
        float $marginLeft,
        float $bandWidth
    ): bool {
        if ($inkMap === null) {
            // Nothing measured: keep the conservative ascent budget so a name
            // still cannot climb off the top of a template we cannot read.
            return $this->nameBlockAscent($size, count($lines)) <= self::NAME_MAX_ASCENT_MM;
        }

        $lineHeight = $size * self::POINT_IN_MM * self::NAME_LINE_HEIGHT_RATIO;
        $capHeight = $size * self::POINT_IN_MM * self::NAME_CAP_HEIGHT_RATIO;
        $topBaseline = $baselineY - ((count($lines) - 1) * $lineHeight);

        foreach ($lines as $index => $line) {
            $lineWidth = $pdf->GetStringWidth($line);
            $x = match ($alignment) {
                'left' => $marginLeft,
                'right' => max(0.0, $marginLeft + $bandWidth - $lineWidth),
                default => max(0.0, $marginLeft + (($bandWidth - $lineWidth) / 2)),
            };
            $lineBaseline = $topBaseline + ($index * $lineHeight);

            if (!TemplateNameBand::regionIsClear(
                $inkMap,
                $x,
                $lineBaseline - $capHeight,
                $lineWidth,
                $capHeight
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * How far a name block reaches above its baseline: one line height for each
     * line after the first, plus the cap height of the topmost line.
     */
    private function nameBlockAscent(float $fontSize, int $lineCount): float
    {
        $lineHeight = $fontSize * self::POINT_IN_MM * self::NAME_LINE_HEIGHT_RATIO;

        return (($lineCount - 1) * $lineHeight)
            + ($fontSize * self::POINT_IN_MM * self::NAME_CAP_HEIGHT_RATIO);
    }

    /**
     * Even out a two-line wrap. Greedy wrapping fills the first line and can
     * strand a single word on the second ("... y Alonso / Realonda"); shifting
     * the break to the most even split reads better and costs nothing, since
     * both halves already fit by construction.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function balanceNameLines(Fpdi $pdf, string $nameText, array $lines, float $maxWidth): array
    {
        if (count($lines) !== 2) {
            return $lines;
        }

        $words = preg_split('/\s+/u', trim($nameText), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) < 3) {
            return $lines;
        }

        $best = $lines;
        $bestSpread = INF;
        for ($split = 1; $split < count($words); $split++) {
            $first = implode(' ', array_slice($words, 0, $split));
            $second = implode(' ', array_slice($words, $split));
            $firstWidth = $pdf->GetStringWidth($first);
            $secondWidth = $pdf->GetStringWidth($second);
            if ($firstWidth > $maxWidth || $secondWidth > $maxWidth) {
                continue;
            }

            $spread = abs($firstWidth - $secondWidth);
            if ($spread < $bestSpread) {
                $bestSpread = $spread;
                $best = [$first, $second];
            }
        }

        return $best;
    }

    /**
     * Break a name that is still too wide at the smallest allowed size onto
     * several lines, splitting mid-word only for words that cannot fit alone.
     *
     * @return list<string>
     */
    private function wrapNameToWidth(Fpdi $pdf, string $nameText, float $maxWidth): array
    {
        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/u', $nameText, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            foreach ($this->splitNameWordToWidth($pdf, $word, $maxWidth) as $piece) {
                $candidate = $current === '' ? $piece : $current . ' ' . $piece;
                if ($current !== '' && $pdf->GetStringWidth($candidate) > $maxWidth) {
                    $lines[] = $current;
                    $current = $piece;
                    continue;
                }

                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [$nameText] : $lines;
    }

    /**
     * @return list<string>
     */
    private function splitNameWordToWidth(Fpdi $pdf, string $word, float $maxWidth): array
    {
        if ($word === '' || $pdf->GetStringWidth($word) <= $maxWidth) {
            return [$word];
        }

        $segments = [];
        $current = '';
        foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $candidate = $current . $character;
            if ($current !== '' && $pdf->GetStringWidth($candidate) > $maxWidth) {
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

    /**
     * Lowest Y the caption may occupy: the top of the signature block when one
     * is stamped, otherwise the bottom page margin.
     *
     * @param  array<string, mixed>  $pageSize
     */
    private function captionBottomBound(array $pageSize, bool $applyRegionalDirectorESign): float
    {
        $pageHeight = (float) ($pageSize['height'] ?? 0.0);
        $bottom = $pageHeight - self::CAPTION_BOTTOM_GAP;

        if ($applyRegionalDirectorESign) {
            $signatureBox = $this->regionalDirectorESignBox($pageSize);
            if ($signatureBox !== null) {
                $bottom = min($bottom, $signatureBox['y'] - self::CAPTION_BOTTOM_GAP);
            }
        }

        return max(0.0, $bottom);
    }

    /**
     * Shrink the caption font and its line spacing together until the wrapped
     * block fits the height available to it. A caption that already fits keeps
     * the standard size, and one that cannot fit even at the smallest size is
     * still rendered at that size rather than overflowing the page.
     *
     * @param  array<int, array<int, array{text: string, style: string}>>  $paragraphs
     * @return array{0: float, 1: float, 2: array<int, array<int, mixed>>}
     */
    private function fitCaptionToBox(Fpdi $pdf, array $paragraphs, float $maxWidth, float $maxHeight): array
    {
        $fontSize = self::CAPTION_FONT_SIZE;
        $wrapped = null;
        $lineHeight = self::CAPTION_LINE_HEIGHT;
        $lineCount = 0;
        $overflows = false;

        while (true) {
            $lineHeight = self::CAPTION_LINE_HEIGHT * ($fontSize / self::CAPTION_FONT_SIZE);
            $wrapped = [];
            $lineCount = 0;

            foreach ($paragraphs as $lineRuns) {
                if ($lineRuns === []) {
                    // A blank editor line still consumes one line of height.
                    $wrapped[] = [];
                    $lineCount++;
                    continue;
                }

                $paragraphLines = $this->buildStyledPdfLines($pdf, $lineRuns, $maxWidth, $fontSize);
                $wrapped[] = $paragraphLines;
                $lineCount += count($paragraphLines);
            }

            if ($maxHeight <= 0.0 || ($lineCount * $lineHeight) <= $maxHeight) {
                break;
            }

            if ($fontSize <= self::CAPTION_MIN_FONT_SIZE) {
                // Out of room even at the smallest size: the caption is rendered
                // anyway (truncating an official citation would be worse) and the
                // caller is told so it can warn whoever is preparing the batch.
                $overflows = true;
                break;
            }

            $fontSize = max(self::CAPTION_MIN_FONT_SIZE, $fontSize - self::CAPTION_FIT_STEP_PT);
        }

        return [$fontSize, $lineHeight, $wrapped ?? [], [
            'font_size' => $fontSize,
            'line_height' => $lineHeight,
            'lines' => $lineCount,
            'max_lines' => $lineHeight > 0 ? (int) floor($maxHeight / $lineHeight) : 0,
            'overflows' => $overflows,
        ]];
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


    private function stampRegionalDirectorSignatureBlock(Fpdi $pdf, array $pageSize, int $offsetPx = 0): bool
    {
        return $this->stampRegionalDirectorESign($pdf, $pageSize, $offsetPx) !== null;
    }

    private function stampRegionalDirectorESign(Fpdi $pdf, array $pageSize, int $offsetPx = 0): ?array
    {
        $box = $this->regionalDirectorESignBox($pageSize, $offsetPx);
        if ($box === null) {
            return null;
        }

        [$width, $height] = $this->fitImageWithinBox($box['path'], $box['width'], $box['height']);

        $drawX = $box['x'] + (($box['width'] - $width) / 2);
        $drawY = $box['y'] + (($box['height'] - $height) / 2);

        $pdf->Image($box['path'], $drawX, $drawY, $width, $height);

        return [
            'x' => $drawX,
            'y' => $drawY,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Resolved placement of the signature box, shared by the stamping call and
     * by the caption fitter that has to stay clear of it.
     *
     * @param  array<string, mixed>  $pageSize
     * @return array{path: string, x: float, y: float, width: float, height: float}|null
     */
    private function regionalDirectorESignBox(array $pageSize, int $offsetPx = 0): ?array
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

        $pageWidth = (float) ($pageSize['width'] ?? 0.0);
        $pageHeight = (float) ($pageSize['height'] ?? 0.0);

        $xEnv = env('CERT_RD_ESIGN_X');
        $yEnv = env('CERT_RD_ESIGN_Y');
        $yOffsetEnv = env('CERT_RD_ESIGN_Y_OFFSET', 0);
        $yOffset = is_numeric($yOffsetEnv) ? (float) $yOffsetEnv : 0.0;

        $x = is_numeric($xEnv)
            ? (float) $xEnv
            : (($pageWidth - $boxWidth) / 2);
        $y = is_numeric($yEnv)
            ? (float) $yEnv
            : ($pageHeight - $boxHeight - 18);
        $y += $yOffset;
        // Applied before the margin clamp below so a large nudge parks the
        // signature at the page edge instead of running off it.
        $x += $this->offsetPxToMm($offsetPx);

        $margin = 5.0;
        $maxX = max($margin, $pageWidth - $margin - $boxWidth);
        $maxY = max($margin, $pageHeight - $margin - $boxHeight);

        return [
            'path' => $esignPath,
            'x' => min(max($x, $margin), $maxX),
            'y' => min(max($y, $margin), $maxY),
            'width' => $boxWidth,
            'height' => $boxHeight,
        ];
    }

    private function fitImageWithinBox(string $imagePath, float $boxWidth, float $boxHeight): array
    {
        // Same signature image on every page of a batch, so its native size is
        // read once rather than once per participant.
        if (!isset($this->regionalDirectorESignMemo['dimensions'][$imagePath])) {
            $probed = @getimagesize($imagePath);
            $this->regionalDirectorESignMemo['dimensions'][$imagePath] = [
                (float) ($probed[0] ?? 0),
                (float) ($probed[1] ?? 0),
            ];
        }

        [$nativeWidth, $nativeHeight] = $this->regionalDirectorESignMemo['dimensions'][$imagePath];

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

    /**
     * Resolved once per request. This is called twice for every participant of a
     * batch — once to keep the caption clear of the signature, once to stamp it —
     * and each call otherwise costs two `settings` queries plus a handful of
     * filesystem probes, so a 300-name batch was issuing over a thousand
     * redundant queries. The memo is an instance property, so it lives and dies
     * with the request and cannot serve a stale path after a new upload.
     */
    private function resolveRegionalDirectorESignPath(): ?string
    {
        if (!array_key_exists('path', $this->regionalDirectorESignMemo)) {
            $this->regionalDirectorESignMemo['path'] = RegionalDirectorSignatory::resolvedPath();
        }

        return $this->regionalDirectorESignMemo['path'];
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

        // ?all=1 renders every participant so the reviewer can page through the
        // whole batch; without it the preview stays a single representative page.
        $previewAll = $request->boolean('all');
        $names = $previewAll
            ? $this->endorsementParticipantNames($endorsement)
            : [$this->firstEndorsementParticipantName($endorsement)];

        $totalNames = count($names);
        $names = array_slice($names, 0, self::PREVIEW_ALL_MAX_PARTICIPANTS);

        // The caption lives in the endorsement payload (see buildTrainingPayload),
        // not as a column on the endorsement row, so read it from there to match
        // what the final generated certificate prints on approval.
        $endorsementPayload = (array) $endorsement->payload;
        $endorsementMargins = $this->normalizeNameMargins(
            $endorsementPayload['name_margin_left'] ?? null,
            $endorsementPayload['name_margin_right'] ?? null
        );
        $layoutReport = null;

        try {
            $pdfContent = $this->renderStampedPdfForParticipants(
                $storage['absolute'],
                $this->buildPreviewParticipants($names),
                (float) $endorsementMargins[0],
                self::STANDARD_NAME_POS_Y,
                self::STANDARD_NAME_FONT_SIZE,
                self::STANDARD_NAME_FONT_FAMILY,
                $this->normalizeNameAlignment($endorsementPayload['name_alignment'] ?? null),
                true,
                $endorsementPayload['caption_text'] ?? null,
                $endorsementPayload['caption_alignment'] ?? 'center',
                $this->normalizeQrLabelFlag($endorsementPayload['qr_show_code'] ?? null),
                $this->normalizeQrLabelFlag($endorsementPayload['qr_show_link'] ?? null),
                $this->normalizeOffsetPx($endorsementPayload['name_offset_x'] ?? null),
                $this->normalizeOffsetPx($endorsementPayload['signature_offset_x'] ?? null),
                $layoutReport,
                (float) $endorsementMargins[1],
                $this->normalizeOffsetPx($endorsementPayload['name_offset_y'] ?? null)
            );
        } catch (\Throwable $e) {
            report($e);
            abort(422, $e->getMessage());
        }

        return response($pdfContent, 200, $this->previewAllHeaders(
            $this->endorsementPdfFilename($endorsement, $previewAll ? 'preview-all' : 'preview'),
            count($names),
            $totalNames,
            $layoutReport
        ));
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

    /**
     * Every named participant on an endorsement, in file order.
     *
     * @return list<string>
     */
    private function endorsementParticipantNames(CertificateEndorsement $endorsement): array
    {
        if (empty($endorsement->participants_file_path)) {
            abort(404, 'Participants file not available for preview.');
        }

        try {
            $participants = $this->parseParticipantStoragePath((string) $endorsement->participants_file_path);
        } catch (\Throwable $e) {
            abort(422, 'Unable to read participants file for preview.');
        }

        $names = $this->previewParticipantNames($participants);
        if ($names === []) {
            abort(422, 'No participant name found for PDF preview.');
        }

        return $names;
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
