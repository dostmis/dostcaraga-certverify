<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use setasign\Fpdi\Fpdi;
use Throwable;

class RefreshCertificateQrDomain extends Command
{
    protected $signature = 'certificates:refresh-qr-domain
        {--code=* : Certificate code(s) to process}
        {--limit=0 : Maximum certificates to process}
        {--dry-run : Show what would change without writing files}';

    protected $description = 'Re-stamp QR codes on issued certificates using the current APP_URL domain.';

    public function handle(): int
    {
        $query = Certificate::query()
            ->whereNotNull('stamped_pdf_path')
            ->where('stamped_pdf_path', '!=', '');

        $codes = array_values(array_filter(
            array_map(static fn ($value) => trim((string) $value), (array) $this->option('code')),
            static fn ($value) => $value !== ''
        ));

        if (!empty($codes)) {
            $query->whereIn('certificate_code', $codes);
        }

        $limit = max(0, (int) $this->option('limit'));
        if ($limit > 0) {
            $query->limit($limit);
        }

        $certs = $query->orderBy('id')->get();
        if ($certs->isEmpty()) {
            $this->info('No certificates matched the given filters.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $baseUrl = rtrim((string) config('app.url'), '/');

        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($certs as $cert) {
            $path = (string) $cert->stamped_pdf_path;
            $storage = $this->resolveCertificateStorage($path);
            if (!$storage) {
                $this->warn("SKIP {$cert->certificate_code}: stamped PDF missing ({$path}).");
                $skipped++;
                continue;
            }

            if (empty($cert->public_token)) {
                $this->warn("SKIP {$cert->certificate_code}: missing public token.");
                $skipped++;
                continue;
            }

            $verifyUrl = $baseUrl . route('cert.verify', ['t' => $cert->public_token], false);

            if ($dryRun) {
                $this->line("DRY {$cert->certificate_code}: {$verifyUrl}");
                $updated++;
                continue;
            }

            $absPath = $storage['absolute'];

            try {
                $pdfContent = $this->refreshStampedPdf(
                    $absPath,
                    (string) $cert->certificate_code,
                    $verifyUrl
                );
                file_put_contents($absPath, $pdfContent);
                $this->line("OK  {$cert->certificate_code}");
                $updated++;
            } catch (Throwable $e) {
                $this->error("FAIL {$cert->certificate_code}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Processed: {$certs->count()}");
        $this->info("Updated: {$updated}");
        $this->info("Skipped: {$skipped}");
        $this->info("Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveCertificateStorage(string $path): ?array
    {
        if ($path === '') {
            return null;
        }

        $localDisk = Storage::disk('local');
        if ($localDisk->exists($path)) {
            return [
                'absolute' => $localDisk->path($path),
            ];
        }

        $publicDisk = Storage::disk('public');
        if ($publicDisk->exists($path)) {
            return [
                'absolute' => $publicDisk->path($path),
            ];
        }

        return null;
    }

    private function refreshStampedPdf(string $sourceAbs, string $codeText, string $verifyUrl): string
    {
        $qrPng = QrCode::format('png')->size(220)->margin(1)->generate($verifyUrl);

        $tmpQr = storage_path('app/tmp_qr_' . uniqid('', true) . '.png');
        @mkdir(dirname($tmpQr), 0777, true);
        file_put_contents($tmpQr, $qrPng);

        $pdf = new Fpdi();
        $converted = null;

        try {
            $pageCount = $pdf->setSourceFile($sourceAbs);
        } catch (Throwable $e) {
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

            $qrSize = 20;
            $margin = 10;
            $x = $size['width'] - $qrSize - $margin;

            $textOffset = 4;
            $requiredBottom = $qrSize + $textOffset + 9;
            $maxY = $size['height'] - $margin - $requiredBottom;
            $y = min($size['height'] - $qrSize - $margin, $maxY);
            $y = max($margin, $y);

            $pdf->Image($tmpQr, $x, $y, $qrSize, $qrSize);

            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(0, 0, 0);
            $textWidth = $pdf->GetStringWidth($codeText);
            $textX = $x + ($qrSize - $textWidth) / 2;
            $textX = max($margin, min($textX, $size['width'] - $margin - $textWidth));
            $textY = $y + $qrSize + $textOffset;
            $pdf->Text($textX, $textY, $codeText);

            $pdf->SetFont('Helvetica', '', 5.5);
            $linkText = $verifyUrl;
            $linkWidth = $pdf->GetStringWidth($linkText);
            $linkX = max($margin, $size['width'] - $margin - $linkWidth);
            $linkY = $textY + 3.5;
            $pdf->Text($linkX, $linkY, $linkText);
        }

        @unlink($tmpQr);
        if ($converted) {
            @unlink($converted);
        }

        return $pdf->Output('S');
    }

    private function convertPdfWithGhostscript(string $sourceAbs): string
    {
        $tmpDir = storage_path('app/tmp');
        @mkdir($tmpDir, 0777, true);
        $outPath = $tmpDir . '/fpdi_' . uniqid('', true) . '.pdf';

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
}
