<?php

namespace Tests\Feature\Admin\Concerns;

use App\Models\Certificate;
use App\Models\CertificateEndorsement;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * Shared setup for the queued certificate generation tests.
 */
trait GeneratesCertificatePackages
{
    protected string $templatePath = 'endorsements/templates/template.pdf';

    /**
     * Point the queue at the database driver and stage a template.
     *
     * The database driver is deliberate: `sync` runs jobs inline during
     * dispatch, which bypasses the batch semantics — halt-on-failure, the
     * WithoutOverlapping middleware, and the then/catch/finally callbacks —
     * that this whole feature depends on.
     */
    protected function prepareGenerationEnvironment(): void
    {
        Storage::fake('local');
        config()->set('queue.default', 'database');
        config()->set('services.telegram_bot.enabled', false);

        Storage::disk('local')->put($this->templatePath, $this->minimalPdf());
    }

    /**
     * Drain the queue in-process, standing in for a running worker.
     */
    protected function runQueueWorker(): void
    {
        Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--tries' => 1,
            '--quiet' => true,
        ]);
    }

    /**
     * @param  array<int, string>  $participantNames
     */
    protected function makeEndorsement(array $participantNames, array $payloadOverrides = []): CertificateEndorsement
    {
        $participantsPath = 'endorsements/participants/' . uniqid('p', true) . '.csv';
        Storage::disk('local')->put(
            $participantsPath,
            "name\n" . implode("\n", $participantNames) . "\n"
        );

        return CertificateEndorsement::create([
            'status' => CertificateEndorsement::STATUS_ENDORSED,
            'submitted_by' => User::factory()->create()->id,
            'participants_count' => count($participantNames),
            'participants_file_path' => $participantsPath,
            'template_pdf_path' => $this->templatePath,
            'payload' => array_merge([
                'training_title' => 'Community Innovation Workshop',
                'training_date_from' => '2026-03-01',
                'training_date_to' => '2026-03-02',
                'issuing_office' => 'DOST Caraga - Fields Operation Division',
                'dost_program' => 'LGIA (Local Grants-in-Aid Program)',
            ], $payloadOverrides),
        ]);
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, array<string, mixed>>
     */
    protected function participants(array $names): array
    {
        return array_map(fn (string $name) => ['name' => $name], $names);
    }

    /**
     * A certificate that predates the run under test and must survive it.
     */
    protected function seedHistoricCertificate(string $code, string $createdAt): Certificate
    {
        $certificate = Certificate::create([
            'certificate_code' => $code,
            'public_token' => (string) \Illuminate\Support\Str::uuid(),
            'participant_name' => 'Historic Participant ' . $code,
            'training_title' => 'Community Innovation Workshop',
            'training_date' => '2026-03-01',
            'issuing_office' => 'DOST Caraga - Fields Operation Division',
            'status' => 'valid',
        ]);

        // created_at drives the batch segment of the code, so it has to be
        // forced rather than left to the insert timestamp.
        Certificate::withoutTimestamps(fn () => $certificate->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save());

        return $certificate->fresh();
    }

    protected function minimalPdf(): string
    {
        return <<<'PDF'
%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Count 1 /Kids [3 0 R] >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] >>
endobj
xref
0 4
0000000000 65535 f
0000000009 00000 n
0000000058 00000 n
0000000115 00000 n
trailer
<< /Size 4 /Root 1 0 R >>
startxref
178
%%EOF
PDF;
    }
}
