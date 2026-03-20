<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateCertificateFilesToPrivate extends Command
{
    protected $signature = 'certificates:migrate-private-storage {--dry-run : Show planned changes without moving files}';

    protected $description = 'Move certificate source/stamped files from public disk to private local disk.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $certs = Certificate::query()
            ->where(function ($query) {
                $query->whereNotNull('source_pdf_path')
                    ->where('source_pdf_path', '!=', '')
                    ->orWhereNotNull('stamped_pdf_path')
                    ->where('stamped_pdf_path', '!=', '');
            })
            ->orderBy('id')
            ->get(['id', 'certificate_code', 'source_pdf_path', 'stamped_pdf_path']);

        if ($certs->isEmpty()) {
            $this->info('No certificate file paths found.');
            return self::SUCCESS;
        }

        $publicDisk = Storage::disk('public');
        $localDisk = Storage::disk('local');

        $moved = 0;
        $missing = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($certs as $cert) {
            foreach (['source_pdf_path', 'stamped_pdf_path'] as $field) {
                $path = (string) ($cert->{$field} ?? '');
                if ($path === '') {
                    continue;
                }

                if ($localDisk->exists($path)) {
                    if ($publicDisk->exists($path) && !$dryRun) {
                        $publicDisk->delete($path);
                    }
                    $skipped++;
                    continue;
                }

                if (!$publicDisk->exists($path)) {
                    $this->warn("MISSING {$cert->certificate_code} {$field}: {$path}");
                    $missing++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("DRY {$cert->certificate_code} {$field}: {$path}");
                    $moved++;
                    continue;
                }

                $stream = $publicDisk->readStream($path);
                if (!is_resource($stream)) {
                    $this->error("FAIL {$cert->certificate_code} {$field}: cannot read {$path}");
                    $failed++;
                    continue;
                }

                $ok = $localDisk->writeStream($path, $stream);
                fclose($stream);

                if (!$ok) {
                    $this->error("FAIL {$cert->certificate_code} {$field}: cannot write {$path}");
                    $failed++;
                    continue;
                }

                $publicDisk->delete($path);
                $moved++;
            }
        }

        $this->newLine();
        $this->info('Migration summary');
        $this->line('Moved: ' . $moved);
        $this->line('Already private/skipped: ' . $skipped);
        $this->line('Missing on public: ' . $missing);
        $this->line('Failed: ' . $failed);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

