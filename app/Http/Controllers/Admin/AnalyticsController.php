<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Certificate analytics export (CSV/XLSX).
 *
 * The interactive analytics dashboard moved to AnalyticsDashboardController +
 * the `analytics` materialized views; this controller now only backs the
 * bulk analytics export still linked from the admin action menu.
 */
class AnalyticsController extends Controller
{
    public function export(Request $request)
    {
        [$query] = $this->baseQuery($request);
        $format = strtolower($request->get('format', 'xlsx'));

        $data = (clone $query)
            ->orderByDesc('created_at')
            ->get([
                'certificate_code', 'participant_name', 'gender', 'age', 'industry',
                'region', 'province', 'city_municipality', 'barangay', 'block_lot_purok',
                'training_title', 'training_date', 'training_date_to', 'issuing_office',
                'status', 'created_at',
            ]);

        $fileNameBase = 'certificates_analytics_'.now('Asia/Manila')->format('Ymd_His');
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
            $fileName = $fileNameBase.'.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ];

            $callback = function () use ($data, $mapRow) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, [
                    'Certificate Code', 'Participant Name', 'Gender', 'Age', 'Industry', 'Region', 'Province', 'City/Municipality',
                    'Barangay', 'Block/Lot/Purok', 'Training Title', 'Training Date', 'Training Date To', 'Issuing Office', 'Status', 'Created At',
                ]);
                foreach ($data as $row) {
                    fputcsv($handle, $mapRow($row));
                }
                fclose($handle);
            };

            return Response::stream($callback, 200, $headers);
        }

        // XLSX
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Certificates');
        $sheet->fromArray([
            ['Certificate Code', 'Participant Name', 'Gender', 'Age', 'Industry', 'Region', 'Province', 'City/Municipality', 'Barangay', 'Block/Lot/Purok', 'Training Title', 'Training Date', 'Training Date To', 'Issuing Office', 'Status', 'Created At'],
        ], null, 'A1');

        $rowIndex = 2;
        foreach ($data as $row) {
            $sheet->fromArray([$mapRow($row)], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer->save($tmp);
        $fileName = $fileNameBase.'.xlsx';

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
            $q = '%'.$filters['q'].'%';
            $query->where(function ($sub) use ($q) {
                $sub->where('certificate_code', 'like', $q)
                    ->orWhere('participant_name', 'like', $q)
                    ->orWhere('training_title', 'like', $q)
                    ->orWhere('issuing_office', 'like', $q);
            });
        }

        return [$query, $filters];
    }

    private function sanitizeForSpreadsheetCell($value)
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = ltrim($value);
        if ($trimmed !== '' && preg_match('/^[=\-+@]/', $trimmed) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
