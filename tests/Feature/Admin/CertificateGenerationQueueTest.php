<?php

namespace Tests\Feature\Admin;

use App\Jobs\GenerateCertificateJob;
use App\Mail\CertificateReadyMail;
use App\Models\Certificate;
use App\Models\CertificateEndorsement;
use App\Services\CertificateGenerationService;
use App\Services\EndorsementGenerationCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Admin\Concerns\GeneratesCertificatePackages;
use Tests\TestCase;

class CertificateGenerationQueueTest extends TestCase
{
    use GeneratesCertificatePackages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareGenerationEnvironment();
    }

    /**
     * The one test that must never be skipped.
     *
     * Certificate codes are printed on issued PDFs and are the lookup key for
     * /print/{code} and admin search. Corrupting them is not recoverable by
     * redeploying.
     */
    public function test_queued_generation_continues_the_existing_code_sequence(): void
    {
        $today = now('Asia/Manila')->toDateString();

        // Two certificates already issued today, in this package's prefix.
        $this->seedHistoricCertificate('2026-FOD-CIW-01-001', $today.' 08:00:00');
        $this->seedHistoricCertificate('2026-FOD-CIW-01-002', $today.' 08:05:00');

        // And one from a previous day, which must be untouched and must not
        // affect today's batch segment.
        $historic = $this->seedHistoricCertificate('2026-FOD-CIW-01-001-OLD', '2026-03-01 08:00:00');

        $names = ['Ana Cruz', 'Ben Diaz', 'Cara Evasco'];
        $endorsement = $this->makeEndorsement($names);

        $this->generate($endorsement, $names);

        $generated = Certificate::query()
            ->where('endorsement_id', $endorsement->id)
            ->orderBy('endorsement_participant_index')
            ->pluck('certificate_code')
            ->all();

        // createCertificate derives the second-to-last segment from a count
        // of certificates created today, so each certificate advances it and
        // the trailing sequence stays 001. Two rows already exist for today,
        // so a correct run starts at 03 — a run that restarted the sequence
        // would produce 01 here and collide with issued certificates.
        $this->assertSame([
            '2026-FOD-CIW-03-001',
            '2026-FOD-CIW-04-001',
            '2026-FOD-CIW-05-001',
        ], $generated);

        // Historic rows survive untouched.
        $this->assertDatabaseHas('certificates', [
            'id' => $historic->id,
            'certificate_code' => '2026-FOD-CIW-01-001-OLD',
            'endorsement_id' => null,
        ]);

        // No duplicates anywhere.
        $codes = Certificate::pluck('certificate_code');
        $this->assertSame($codes->count(), $codes->unique()->count());
    }

    /**
     * Phase 1's entire gain depends on this. If a later refactor moves
     * normalization into the job, Ghostscript runs once per certificate and
     * the 4.4x improvement silently disappears.
     */
    public function test_the_template_is_normalized_once_per_batch(): void
    {
        $names = ['Ana Cruz', 'Ben Diaz', 'Cara Evasco', 'Dan Flores'];
        $endorsement = $this->makeEndorsement($names);

        $this->partialMock(
            CertificateGenerationService::class,
            function ($mock) {
                $mock->shouldReceive('normalizeTemplateForFpdi')->once()->passthru();
            }
        );

        $this->generate($endorsement, $names);

        $this->assertSame(4, Certificate::where('endorsement_id', $endorsement->id)->count());
    }

    public function test_every_job_in_a_batch_stamps_from_the_same_template_path(): void
    {
        $names = ['Ana Cruz', 'Ben Diaz', 'Cara Evasco'];
        $endorsement = $this->makeEndorsement($names);

        $coordinator = app(EndorsementGenerationCoordinator::class);
        $this->assertTrue($coordinator->claim($endorsement, null));
        $coordinator->dispatchGeneration($endorsement, $this->participants($names), null);

        $paths = collect(\Illuminate\Support\Facades\DB::table('jobs')->pluck('payload'))
            ->map(function (string $payload) {
                $command = json_decode($payload, true)['data']['command'] ?? '';
                $job = unserialize($command);

                return $job instanceof GenerateCertificateJob ? $job->templatePath : null;
            })
            ->filter()
            ->unique();

        $this->assertCount(1, $paths, 'All jobs in a batch must share one normalized template.');
    }

    public function test_a_job_replayed_after_its_insert_committed_creates_no_duplicate(): void
    {
        $names = ['Ana Cruz'];
        $endorsement = $this->makeEndorsement($names);

        $this->generate($endorsement, $names);
        $this->assertSame(1, Certificate::where('endorsement_id', $endorsement->id)->count());

        // Replay the exact job a retry would run.
        $job = new GenerateCertificateJob(
            endorsementId: $endorsement->id,
            participantIndex: 0,
            participant: ['name' => 'Ana Cruz'],
            recipientId: null,
            payload: (array) $endorsement->fresh()->payload,
            templatePath: $this->templatePath,
        );
        $job->handle(app(CertificateGenerationService::class));

        $this->assertSame(
            1,
            Certificate::where('endorsement_id', $endorsement->id)->count(),
            'The idempotency guard must stop a retry issuing a second certificate.'
        );
    }

    public function test_a_double_submitted_approval_claims_the_package_only_once(): void
    {
        $endorsement = $this->makeEndorsement(['Ana Cruz']);
        $coordinator = app(EndorsementGenerationCoordinator::class);

        $this->assertTrue($coordinator->claim($endorsement, null));
        $this->assertFalse(
            $coordinator->claim($endorsement->fresh(), null),
            'A second approval must not claim a package that is already generating.'
        );

        $this->assertSame(
            CertificateEndorsement::STATUS_GENERATING,
            $endorsement->fresh()->status
        );
    }

    public function test_generation_is_serialized_by_a_single_global_lock(): void
    {
        $job = new GenerateCertificateJob(
            endorsementId: 1,
            participantIndex: 0,
            participant: ['name' => 'Ana Cruz'],
            recipientId: null,
            payload: [],
            templatePath: $this->templatePath,
        );

        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);

        // Same key for every job, so the lock serializes across endorsements
        // and across workers — not just within one package.
        $other = new GenerateCertificateJob(
            endorsementId: 99,
            participantIndex: 7,
            participant: ['name' => 'Ben Diaz'],
            recipientId: null,
            payload: [],
            templatePath: $this->templatePath,
        );

        $this->assertSame(
            $middleware[0]->getLockKey($job),
            $other->middleware()[0]->getLockKey($other)
        );

        // The key must not vary by job class either, or a future
        // certificate-creating job would race this one.
        $this->assertSame(
            'laravel-queue-overlap:'.GenerateCertificateJob::CODE_LOCK,
            $middleware[0]->getLockKey($job)
        );
    }

    public function test_a_job_is_released_rather_than_run_while_the_lock_is_held(): void
    {
        $names = ['Ana Cruz'];
        $endorsement = $this->makeEndorsement($names);

        $coordinator = app(EndorsementGenerationCoordinator::class);
        $coordinator->claim($endorsement, null);
        $coordinator->dispatchGeneration($endorsement, $this->participants($names), null);

        // Stand in for a second worker mid-certificate.
        $lock = Cache::lock('laravel-queue-overlap:'.GenerateCertificateJob::CODE_LOCK, 120);
        $this->assertTrue($lock->get());

        try {
            $this->runQueueWorker();

            $this->assertSame(
                0,
                Certificate::where('endorsement_id', $endorsement->id)->count(),
                'A job must not generate while another worker holds the code lock.'
            );
        } finally {
            $lock->release();
        }

        // releaseAfter(5) puts the loser back with a delay, so the released
        // job is not available again until that window passes.
        $this->travel(6)->seconds();
        $this->runQueueWorker();
        $this->assertSame(
            1,
            Certificate::where('endorsement_id', $endorsement->id)->count(),
            'A released job must be retried once the lock is free, not dropped.'
        );
    }

    public function test_a_successful_batch_approves_the_package_and_queues_each_email_once(): void
    {
        $names = ['Ana Cruz', 'Ben Diaz'];
        $endorsement = $this->makeEndorsement($names);

        $participants = [
            ['name' => 'Ana Cruz', 'email' => 'ana@example.test'],
            ['name' => 'Ben Diaz', 'email' => 'ben@example.test'],
        ];

        Mail::fake();

        $coordinator = app(EndorsementGenerationCoordinator::class);
        $coordinator->claim($endorsement, null);
        $coordinator->dispatchGeneration($endorsement, $participants, null);
        $this->runQueueWorker();

        $endorsement->refresh();
        $this->assertSame(CertificateEndorsement::STATUS_RD_APPROVED, $endorsement->status);
        $this->assertSame(2, $endorsement->generated_count);
        $this->assertNull($endorsement->generation_error);

        Mail::assertSent(CertificateReadyMail::class, 2);
        Mail::assertSent(CertificateReadyMail::class, fn ($mail) => $mail->hasTo('ana@example.test'));
        Mail::assertSent(CertificateReadyMail::class, fn ($mail) => $mail->hasTo('ben@example.test'));
    }

    public function test_the_normalized_template_is_removed_after_a_successful_batch(): void
    {
        $names = ['Ana Cruz', 'Ben Diaz'];
        $endorsement = $this->makeEndorsement($names);

        $this->generate($endorsement, $names);

        $this->assertEmpty(
            Storage::disk('local')->files(EndorsementGenerationCoordinator::TEMPLATE_DIR),
            'The batch must delete its normalized template in finally().'
        );
    }

    public function test_progress_reports_the_batch_position(): void
    {
        $names = ['Ana Cruz', 'Ben Diaz', 'Cara Evasco'];
        $endorsement = $this->makeEndorsement($names);

        $coordinator = app(EndorsementGenerationCoordinator::class);
        $coordinator->claim($endorsement, null);
        $coordinator->dispatchGeneration($endorsement, $this->participants($names), null);

        $progress = $coordinator->progress($endorsement->fresh());

        $this->assertSame(CertificateEndorsement::STATUS_GENERATING, $progress['status']);
        $this->assertSame(3, $progress['total']);
        $this->assertSame(0, $progress['processed']);
        $this->assertFalse($progress['finished']);

        $this->runQueueWorker();

        $progress = $coordinator->progress($endorsement->fresh());
        $this->assertSame(CertificateEndorsement::STATUS_RD_APPROVED, $progress['status']);
        $this->assertSame(3, $progress['processed']);
        $this->assertTrue($progress['finished']);
    }

    /**
     * The direct-RD path stayed synchronous, but its internals moved into the
     * shared service, so it needs regression cover.
     */
    public function test_the_synchronous_path_still_generates_and_records_no_endorsement_link(): void
    {
        $controller = app(\App\Http\Controllers\Admin\CertificateAdminController::class);
        $method = new \ReflectionMethod($controller, 'generateCertificatesFromPayload');
        $method->setAccessible(true);

        $certificates = $method->invoke(
            $controller,
            [
                'training_title' => 'Community Innovation Workshop',
                'training_date_from' => '2026-03-01',
                'issuing_office' => 'DOST Caraga - Fields Operation Division',
            ],
            $this->participants(['Ana Cruz', 'Ben Diaz']),
            $this->templatePath,
            true
        );

        $this->assertCount(2, $certificates);

        foreach ($certificates as $certificate) {
            $this->assertNull(
                $certificate->endorsement_id,
                'A directly generated certificate has no endorsement, so it must never be reachable by rollback.'
            );
            $this->assertNull($certificate->endorsement_participant_index);
            $this->assertTrue(Storage::disk('local')->exists($certificate->stamped_pdf_path));
            $this->assertTrue(Storage::disk('local')->exists($certificate->source_pdf_path));
        }

        $this->assertSame(
            ['Ana Cruz', 'Ben Diaz'],
            array_map(fn ($certificate) => $certificate->participant_name, $certificates)
        );

        // The normalized template is cleaned up by the method's own finally.
        $this->assertEmpty(Storage::disk('local')->files('tmp'));
    }

    /**
     * @param  array<int, string>  $names
     */
    private function generate(CertificateEndorsement $endorsement, array $names): void
    {
        $coordinator = app(EndorsementGenerationCoordinator::class);
        $this->assertTrue($coordinator->claim($endorsement, null));
        $coordinator->dispatchGeneration($endorsement, $this->participants($names), null);
        $this->runQueueWorker();
    }
}
