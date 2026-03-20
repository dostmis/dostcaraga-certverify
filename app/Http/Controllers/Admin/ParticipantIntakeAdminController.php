<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParticipantIntakeEvent;
use App\Models\ParticipantIntake;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ParticipantIntakeAdminController extends Controller
{
    public function index(Request $request)
    {
        [$query, $filters, $eventLinks] = $this->baseQuery($request);

        $intakes = $query->orderByDesc('created_at')->get();

        $intakeEnabled = \App\Models\Setting::getBool('participant_intake_enabled', true);
        $user = $request->user();
        $canEndorse = $this->canEndorse($user);
        $canRegionalDirectorActions = $this->isRegionalDirector($user);
        $activeEventLink = null;
        if ($canEndorse) {
            $selectedEventLink = $eventLinks->firstWhere('id', (int) ($filters['event_id'] ?? 0));
            $activeEventLink = ($selectedEventLink && $selectedEventLink->is_active)
                ? $selectedEventLink
                : ($eventLinks->firstWhere('is_active', true) ?? null);
        }
        $activeEventUrl = $activeEventLink
            ? rtrim((string) config('app.url'), '/') . route('participant.intake', ['token' => $activeEventLink->public_token], false)
            : null;
        $countQuery = $this->scopeQueryForUser($user);
        if (($filters['event_id'] ?? 'all') !== 'all') {
            $countQuery->where('participant_intake_event_id', (int) $filters['event_id']);
        }
        $pendingCount = (clone $countQuery)->where('status', 'pending')->count();
        $doneCount = (clone $countQuery)->whereIn('status', ['done', 'endorsed', 'rd_approved'])->count();

        return view('admin.participant-intakes.index', compact(
            'intakes',
            'filters',
            'intakeEnabled',
            'pendingCount',
            'doneCount',
            'canEndorse',
            'canRegionalDirectorActions',
            'eventLinks',
            'activeEventLink',
            'activeEventUrl'
        ));
    }

    public function createEvent(Request $request)
    {
        $user = $request->user();
        $this->ensureCanExportSelected($user);
        $data = $request->validate([
            'event_name' => ['required', 'string', 'max:255'],
        ]);

        $event = ParticipantIntakeEvent::create([
            'user_id' => $user->id,
            'event_name' => trim((string) $data['event_name']),
            'public_token' => (string) Str::uuid(),
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.participant-intakes.index', ['event_id' => $event->id])
            ->with('success', 'New intake event link created.');
    }

    public function toggleEvent(Request $request, ParticipantIntakeEvent $event)
    {
        $user = $request->user();
        $this->ensureCanExportSelected($user);
        if ($event->user_id !== $user->id) {
            abort(403, 'You can only manage your own intake events.');
        }

        $enabled = $request->boolean('enabled', !$event->is_active);
        $event->update([
            'is_active' => $enabled,
        ]);

        return back()->with('success', 'Intake event link ' . ($enabled ? 'activated' : 'deactivated') . '.');
    }

    public function deleteEvent(Request $request, ParticipantIntakeEvent $event)
    {
        $user = $request->user();
        $this->ensureCanExportSelected($user);
        if ($event->user_id !== $user->id) {
            abort(403, 'You can only delete your own intake events.');
        }

        $eventName = $event->event_name;
        $event->delete();

        return redirect()
            ->route('admin.participant-intakes.index')
            ->with('success', "Intake event link \"{$eventName}\" deleted.");
    }

    public function destroy(Request $request, ParticipantIntake $intake)
    {
        $this->ensureRegionalDirectorAction($request->user());

        if ($intake->status !== 'pending') {
            return back()->with('success', 'Only pending submissions can be deleted.');
        }

        $intake->delete();

        return back()->with('success', 'Submission deleted.');
    }

    public function bulkDelete(Request $request)
    {
        $this->ensureRegionalDirectorAction($request->user());

        $ids = $this->validatedIds($request);
        if (empty($ids)) {
            return back()->with('success', 'No submissions selected.');
        }

        $count = ParticipantIntake::whereIn('id', $ids)
            ->where('status', 'pending')
            ->delete();

        return back()->with('success', "Deleted {$count} submissions.");
    }

    public function toggleIntake(Request $request)
    {
        $this->ensureRegionalDirectorAction($request->user());

        $enabled = $request->boolean('enabled');
        \App\Models\Setting::setValue('participant_intake_enabled', $enabled ? '1' : '0', auth()->id());

        return back()->with('success', 'Participant intake ' . ($enabled ? 'enabled' : 'disabled') . '.');
    }

    public function exportSelected(Request $request)
    {
        $this->ensureCanExportSelected($request->user());
        $user = $request->user();

        $ids = $this->validatedIds($request);
        if (empty($ids)) {
            return back()->with('success', 'No submissions selected for export.');
        }

        $format = strtolower($request->get('format', 'csv'));
        $statusFilter = strtolower((string) $request->input('status', 'pending'));
        $rowsQuery = ParticipantIntake::query()
            ->whereIn('id', $ids)
            ->where('owner_user_id', $user->id);

        if ($statusFilter === 'done') {
            $rowsQuery->whereIn('status', ['done', 'endorsed', 'rd_approved']);
        } else {
            $rowsQuery->where('status', 'pending');
        }

        $rows = $rowsQuery
            ->orderBy('participant_name')
            ->get();
        if ($rows->isEmpty()) {
            return back()->with('success', 'No matching submissions selected for export.');
        }

        if ($statusFilter !== 'done') {
            ParticipantIntake::whereIn('id', $rows->pluck('id')->all())
                ->update([
                    'status' => 'done',
                    'reviewed_at' => now(),
                    'reviewed_by' => auth()->id(),
                ]);
        }

        $fileNameBase = 'participant_intakes_selected_' . now('Asia/Manila')->format('Ymd_His');

        return $this->exportRows($rows, $format, $fileNameBase);
    }

    public function export(Request $request)
    {
        $this->ensureRegionalDirectorAction($request->user());

        [$query] = $this->baseQuery($request, false);
        $format = strtolower($request->get('format', 'xlsx'));

        $rows = (clone $query)
            ->whereIn('status', ['done', 'endorsed', 'rd_approved'])
            ->orderBy('participant_name')
            ->get();

        $fileNameBase = 'participant_intakes_' . now('Asia/Manila')->format('Ymd_His');
        return $this->exportRows($rows, $format, $fileNameBase);
    }

    private function exportRows($rows, string $format, string $fileNameBase)
    {
        $headers = $this->intakeExportHeaders();
        $mapRow = fn ($row) => $this->intakeExportRow($row);

        if ($format === 'csv') {
            $fileName = $fileNameBase . '.csv';
            $respHeaders = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ];

            $callback = function () use ($rows, $headers, $mapRow) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, $headers);
                foreach ($rows as $row) {
                    fputcsv($handle, $mapRow($row));
                }
                fclose($handle);
            };

            return Response::stream($callback, 200, $respHeaders);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Participants');
        $sheet->fromArray([$headers], null, 'A1');

        $rowIndex = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([$mapRow($row)], null, 'A' . $rowIndex);
            $rowIndex++;
        }

        foreach (range('A', 'X') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer->save($tmp);

        $fileName = $fileNameBase . '.xlsx';
        return response()->download($tmp, $fileName)->deleteFileAfterSend(true);
    }

    private function intakeExportHeaders(): array
    {
        return [
            'Last Name', 'First Name', 'Middle Initial', 'Participant Name', 'Email',
            'Contact Number', 'Sex', 'Age Range', 'Organization Name', 'Affiliation/Sector', 'Region', 'Province/State',
            'City/Municipality', 'Barangay', 'Block/Lot/Purok',
            'PWD', '4PS Beneficiary', 'ELCAC Community', 'DOST Program Beneficiary/Recipient',
            'Directly Employed Programs', 'Previously Attended DOST Caraga Training',
            'Interested DOST Services', 'Interested DOST Services (Others)', 'Position/Designation',
        ];
    }

    private function intakeExportRow($row): array
    {
        return array_map(fn ($cell) => $this->sanitizeForSpreadsheetCell($cell), [
            $row->last_name,
            $row->first_name,
            $row->middle_initial,
            $row->participant_name,
            $row->email,
            $row->contact_number,
            $row->gender,
            $row->age_range,
            $row->organization_name,
            $row->industry,
            $row->region,
            $row->province,
            $row->city_municipality,
            $row->barangay,
            $row->block_lot_purok,
            $row->pwd_status,
            $row->is_4ps_beneficiary,
            $row->is_elcac_community,
            implode(', ', (array) ($row->dost_program_beneficiary ?? [])),
            implode(', ', (array) ($row->directly_employed_programs ?? [])),
            $row->has_attended_dost_training,
            implode(', ', (array) ($row->interested_dost_services ?? [])),
            $row->interested_dost_services_other,
            $row->position_designation,
        ]);
    }

    private function baseQuery(Request $request, bool $applyStatus = true): array
    {
        $user = $request->user();
        $eventLinks = $this->eventLinksForUser($user);
        $firstEvent = $eventLinks->firstWhere('is_active', true) ?? $eventLinks->first();
        $defaultEventId = $this->isRegionalDirector($user)
            ? 'all'
            : ((string) optional($firstEvent)->id ?: 'all');
        $defaultStatus = 'pending';
        $filters = [
            'event_id' => (string) $request->input('event_id', $defaultEventId),
            'status' => $request->input('status', $defaultStatus),
            'q' => $request->input('q'),
        ];

        $query = $this->scopeQueryForUser($user);
        if ($filters['event_id'] !== 'all') {
            $query->where('participant_intake_event_id', (int) $filters['event_id']);
        }

        $allowedStatuses = ['pending', 'done', 'all'];
        if (!in_array($filters['status'], $allowedStatuses, true)) {
            $filters['status'] = $defaultStatus;
        }

        if ($applyStatus) {
            if ($filters['status'] === 'pending') {
                $query->where('status', 'pending');
            } elseif ($filters['status'] === 'done') {
                $query->whereIn('status', ['done', 'endorsed', 'rd_approved']);
            }
        }

        if ($filters['q']) {
            $q = '%' . $filters['q'] . '%';
            $query->where(function ($sub) use ($q) {
                $sub->where('participant_name', 'ilike', $q)
                    ->orWhere('last_name', 'ilike', $q)
                    ->orWhere('first_name', 'ilike', $q)
                    ->orWhere('middle_initial', 'ilike', $q)
                    ->orWhere('email', 'ilike', $q)
                    ->orWhere('contact_number', 'ilike', $q)
                    ->orWhere('region', 'ilike', $q)
                    ->orWhere('province', 'ilike', $q)
                    ->orWhere('city_municipality', 'ilike', $q)
                    ->orWhere('barangay', 'ilike', $q)
                    ->orWhere('age_range', 'ilike', $q)
                    ->orWhere('industry', 'ilike', $q);
            });
        }

        return [$query, $filters, $eventLinks];
    }

    private function validatedIds(Request $request): array
    {
        $data = $request->validate([
            'selected' => ['nullable', 'array'],
            'selected.*' => ['integer'],
        ]);

        return $data['selected'] ?? [];
    }

    private function sanitizeForSpreadsheetCell($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = ltrim($value);
        if ($trimmed !== '' && preg_match('/^[=\-+@]/', $trimmed) === 1) {
            return "'" . $value;
        }

        return $value;
    }

    private function ensureCanExportSelected(?User $user): void
    {
        if (!$this->canEndorse($user)) {
            abort(403, 'Only organizer/supervising unit roles can export selected participant submissions.');
        }
    }

    private function ensureRegionalDirectorAction(?User $user): void
    {
        if (!$this->isRegionalDirector($user)) {
            abort(403, 'Only the Regional Director can perform this action.');
        }
    }

    private function canEndorse(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasAnyRole(User::endorserRoles());
    }

    private function isRegionalDirector(?User $user): bool
    {
        return $user ? $user->isRegionalDirector() : false;
    }

    private function eventLinksForUser(?User $user)
    {
        $query = ParticipantIntakeEvent::query();
        if ($this->canEndorse($user) && !$this->isRegionalDirector($user)) {
            $query->where('user_id', $user->id);
        }

        return $query->orderByDesc('created_at')->get();
    }

    private function scopeQueryForUser(?User $user)
    {
        $query = ParticipantIntake::query();
        if ($this->canEndorse($user) && !$this->isRegionalDirector($user)) {
            $query->where('owner_user_id', $user->id);
        }

        return $query;
    }
}
