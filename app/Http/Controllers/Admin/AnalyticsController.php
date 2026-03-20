<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\ParticipantIntake;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        [$query, $filters] = $this->baseQuery($request);
        $intakeQuery = $this->baseIntakeQuery($filters);

        $total = (clone $query)->count();
        $uniqueParticipants = (clone $query)->distinct('participant_name')->count('participant_name');

        $firstTimeParticipants = (clone $intakeQuery)
            ->where('has_attended_dost_training', 'No')
            ->count();

        $repeatParticipants = (clone $intakeQuery)
            ->where('has_attended_dost_training', 'Yes')
            ->count();

        $firstTimeRepeatBase = max($firstTimeParticipants + $repeatParticipants, 1);
        $firstTimePct = round(($firstTimeParticipants / $firstTimeRepeatBase) * 100, 1);
        $repeatPct = round(($repeatParticipants / $firstTimeRepeatBase) * 100, 1);

        $byGender = (clone $query)
            ->selectRaw('gender, COUNT(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender');

        $byIndustry = (clone $query)
            ->selectRaw('industry, COUNT(*) as total')
            ->groupBy('industry')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $byRegion = (clone $query)
            ->selectRaw('region, province, COUNT(*) as total')
            ->groupBy('region', 'province')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $byOffice = (clone $query)
            ->selectRaw('issuing_office, COUNT(*) as total')
            ->groupBy('issuing_office')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $byTraining = (clone $query)
            ->selectRaw('training_title, COUNT(*) as total')
            ->groupBy('training_title')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $byTopic = (clone $query)
            ->selectRaw('topic, COUNT(*) as total')
            ->groupBy('topic')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $timeline = (clone $query)
            ->selectRaw('training_date as d, COUNT(*) as total')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $latest = (clone $query)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $topParticipants = (clone $query)
            ->selectRaw('participant_name, COUNT(*) as total, MAX(created_at) as last_issued_at')
            ->whereNotNull('participant_name')
            ->where('participant_name', '<>', '')
            ->groupBy('participant_name')
            ->orderByDesc('total')
            ->orderByDesc('last_issued_at')
            ->limit(5)
            ->get();

        $inclusionHeatmap = (clone $intakeQuery)
            ->selectRaw('region, province')
            ->selectRaw("SUM(CASE WHEN pwd_status = 'Yes' THEN 1 ELSE 0 END) as pwd_total")
            ->selectRaw("SUM(CASE WHEN is_4ps_beneficiary = 'Yes' THEN 1 ELSE 0 END) as four_ps_total")
            ->selectRaw("SUM(CASE WHEN is_elcac_community = 'Yes' THEN 1 ELSE 0 END) as elcac_total")
            ->selectRaw(
                "(SUM(CASE WHEN pwd_status = 'Yes' THEN 1 ELSE 0 END)
                + SUM(CASE WHEN is_4ps_beneficiary = 'Yes' THEN 1 ELSE 0 END)
                + SUM(CASE WHEN is_elcac_community = 'Yes' THEN 1 ELSE 0 END)) as inclusion_total"
            )
            ->groupBy('region', 'province')
            ->orderByDesc('inclusion_total')
            ->limit(12)
            ->get()
            ->map(function ($row) {
                $row->region_label = $row->region ?: 'Unspecified';
                $row->province_label = $row->province ?: 'Unspecified';
                return $row;
            });

        $heatmapMax = (int) $inclusionHeatmap
            ->flatMap(fn ($row) => [(int) $row->pwd_total, (int) $row->four_ps_total, (int) $row->elcac_total])
            ->max();
        $heatmapMax = max($heatmapMax, 1);

        $industryChart = [
            'labels' => $byIndustry->pluck('industry')->map(fn ($v) => $v ?: 'Unspecified')->values(),
            'data' => $byIndustry->pluck('total')->values(),
        ];

        $genderChart = [
            'labels' => ['Male', 'Female', 'Unspecified'],
            'data' => [
                $byGender['Male'] ?? 0,
                $byGender['Female'] ?? 0,
                ($byGender[''] ?? 0) + ($byGender[null] ?? 0),
            ],
        ];

        $timelineChart = [
            'labels' => $timeline->pluck('d')->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('Y-m-d'))->values(),
            'data' => $timeline->pluck('total')->values(),
        ];

        $topicChart = [
            'labels' => $byTopic->pluck('topic')->map(fn ($v) => $v ?: 'Unspecified')->values(),
            'data' => $byTopic->pluck('total')->values(),
        ];

        return view('admin.analytics.index', compact(
            'filters', 'total', 'uniqueParticipants', 'byGender', 'byIndustry',
            'byRegion', 'byOffice', 'byTraining', 'byTopic', 'timeline', 'latest',
            'topParticipants', 'industryChart', 'genderChart', 'timelineChart', 'topicChart',
            'firstTimeParticipants', 'repeatParticipants', 'firstTimePct', 'repeatPct',
            'inclusionHeatmap', 'heatmapMax'
        ));
    }

    public function export(Request $request)
    {
        [$query, $filters] = $this->baseQuery($request);
        $format = strtolower($request->get('format', 'xlsx'));

        $data = (clone $query)
            ->orderByDesc('created_at')
            ->get([
                'certificate_code', 'participant_name', 'gender', 'age', 'industry',
                'region', 'province', 'city_municipality', 'barangay', 'block_lot_purok',
                'training_title', 'training_date', 'training_date_to', 'issuing_office',
                'status', 'created_at'
            ]);

        $fileNameBase = 'certificates_analytics_' . now('Asia/Manila')->format('Ymd_His');
        $mapRow = function ($row): array {
            return array_map(fn ($cell) => $this->sanitizeForSpreadsheetCell($cell), [
                $row->certificate_code,
                $row->participant_name,
                $row->gender,
                $row->age,
                $row->industry,
                $row->region,
                $row->province,
                $row->city_municipality,
                $row->barangay,
                $row->block_lot_purok,
                $row->training_title,
                optional($row->training_date)->format('Y-m-d'),
                optional($row->training_date_to)->format('Y-m-d'),
                $row->issuing_office,
                $row->status,
                optional($row->created_at)->timezone('Asia/Manila')->format('Y-m-d H:i:s'),
            ]);
        };

        if ($format === 'csv') {
            $fileName = $fileNameBase . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ];

            $callback = function () use ($data, $mapRow) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, [
                    'Certificate Code','Participant Name','Gender','Age','Industry','Region','Province','City/Municipality',
                    'Barangay','Block/Lot/Purok','Training Title','Training Date','Training Date To','Issuing Office','Status','Created At'
                ]);
                foreach ($data as $row) {
                    fputcsv($handle, $mapRow($row));
                }
                fclose($handle);
            };

            return Response::stream($callback, 200, $headers);
        }

        // XLSX
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Certificates');
        $sheet->fromArray([
            ['Certificate Code','Participant Name','Gender','Age','Industry','Region','Province','City/Municipality','Barangay','Block/Lot/Purok','Training Title','Training Date','Training Date To','Issuing Office','Status','Created At']
        ], null, 'A1');

        $rowIndex = 2;
        foreach ($data as $row) {
            $sheet->fromArray([$mapRow($row)], null, 'A' . $rowIndex);
            $rowIndex++;
        }

        foreach (range('A','P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer->save($tmp);
        $fileName = $fileNameBase . '.xlsx';

        return response()->download($tmp, $fileName)->deleteFileAfterSend(true);
    }

    private function baseQuery(Request $request): array
    {
        $filters = [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'region' => $request->input('region'),
            'province' => $request->input('province'),
            'office' => $request->input('office'),
            'industry' => $request->input('industry'),
            'gender' => $request->input('gender'),
            'q' => $request->input('q'),
        ];

        $query = Certificate::query();

        if ($filters['date_from']) {
            $query->whereDate('training_date', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->whereDate('training_date', '<=', $filters['date_to']);
        }
        if ($filters['region']) {
            $query->where('region', $filters['region']);
        }
        if ($filters['province']) {
            $query->where('province', $filters['province']);
        }
        if ($filters['office']) {
            $query->where('issuing_office', $filters['office']);
        }
        if ($filters['industry']) {
            $query->where('industry', $filters['industry']);
        }
        if ($filters['gender']) {
            $query->where('gender', $filters['gender']);
        }
        if ($filters['q']) {
            $q = '%' . $filters['q'] . '%';
            $query->where(function ($sub) use ($q) {
                $sub->where('certificate_code', 'like', $q)
                    ->orWhere('participant_name', 'like', $q)
                    ->orWhere('training_title', 'like', $q)
                    ->orWhere('issuing_office', 'like', $q);
            });
        }

        return [$query, $filters];
    }

    private function baseIntakeQuery(array $filters)
    {
        $query = ParticipantIntake::query();

        if ($filters['date_from']) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if ($filters['region']) {
            $query->where('region', $filters['region']);
        }
        if ($filters['province']) {
            $query->where('province', $filters['province']);
        }
        if ($filters['industry']) {
            $query->where('industry', $filters['industry']);
        }
        if ($filters['gender']) {
            $query->where('gender', $filters['gender']);
        }
        if ($filters['q']) {
            $q = '%' . $filters['q'] . '%';
            $query->where(function ($sub) use ($q) {
                $sub->where('participant_name', 'like', $q)
                    ->orWhere('email', 'like', $q)
                    ->orWhere('contact_number', 'like', $q)
                    ->orWhere('organization_name', 'like', $q)
                    ->orWhere('region', 'like', $q)
                    ->orWhere('province', 'like', $q)
                    ->orWhere('city_municipality', 'like', $q)
                    ->orWhere('barangay', 'like', $q);
            });
        }

        return $query;
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
}
