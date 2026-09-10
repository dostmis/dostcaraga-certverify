<?php

namespace Tests\Feature\Admin;

use App\Models\Certificate;
use App\Models\CertificateEndorsement;
use App\Services\CertificateGenerationService;
use App\Services\EndorsementGenerationCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Admin\Concerns\FailingCertificateGenerationService;
use Tests\Feature\Admin\Concerns\GeneratesCertificatePackages;
use Tests\TestCase;

/**
 * Rollback is the only destructive operation in the async generation feature.
 *
 * The assertion that matters most is that certificates belonging to *other*
 * endorsements survive — that is the guard against a scoping bug turning into
 * a mass delete of issued certificates.
 */
class CertificateGenerationRollbackTest extends TestCase
{
    use GeneratesCertificatePackages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareGenerationEnvironment();
        FailingCertificateGenerationService::reset();
        $this->app->singleton(
            CertificateGenerationService::class,
            fn () => new FailingCertificateGenerationService()
        );
    }

    protected function tearDown(): void
    {
        FailingCertificateGenerationService::reset();
        parent::tearDown();
    }

    public function test_a_mid_batch_failure_deletes_every_certificate_and_pdf_for_the_package(): void
    {
        $names = ['Ana Cruz', 'Ben Diaz', 'Cara Evasco'];
        $endorsement = $this->makeEndorsement($names);

        $this->failGenerationAt('Ben Diaz');
        $this->generateExpectingFailure($endorsement, $names);

        // Assert against the files actually written before the failure, not
        // against paths the test guessed.
        $writtenPaths = $this->pathsWrittenBeforeFailure();

        $this->assertSame(
            0,
            Certificate::where('endorsement_id', $endorsement->id)->count(),
            'Rollback must delete every certificate row the failed run created.'
        );

        $this->assertNotEmpty($writtenPaths, 'The test needs at least one certificate generated before the failure.');
        foreach ($writtenPaths as $path) {
            $this->assertFalse(
                Storage::disk('local')->exists($path),
                "Rollback left an orphaned PDF at {$path}."
            );
        }
    }

    public function test_rollback_leaves_certificates_from_other_endorsements_untouched(): void
    {
        // A package that generated successfully and must survive.
        $survivorNames = ['Zoe Uy'];
        $survivor = $this->makeEndorsement($survivorNames);
        $this->generate($survivor, $survivorNames);

        $survivorCertificate = Certificate::where('endorsement_id', $survivor->id)->sole();
        $this->assertTrue(Storage::disk('local')->exists($survivorCertificate->stamped_pdf_path));

        // A historic certificate with no endorsement link at all.
        $historic = $this->seedHistoricCertificate('2026-FOD-CIW-99-001', '2026-03-01 08:00:00');

        // Now fail a different package.
        $names = ['Ana Cruz', 'Ben Diaz'];
        $doomed = $this->makeEndorsement($names);
        $this->failGenerationAt('Ben Diaz');
        $this->generateExpectingFailure($doomed, $names);

        $this->assertSame(0, Certificate::where('endorsement_id', $doomed->id)->count());

        $this->assertDatabaseHas('certificates', [
            'id' => $survivorCertificate->id,
            'endorsement_id' => $survivor->id,
        ]);
        $this->assertTrue(
            Storage::disk('local')->exists($survivorCertificate->stamped_pdf_path),
            'Rollback must not delete another endorsement PDF.'
        );

        $this->assertDatabaseHas('certificates', [
            'id' => $historic->id,
            'endorsement_id' => null,
        ]);

        $this->assertSame(
            CertificateEndorsement::STATUS_RD_APPROVED,
            $survivor->fresh()->status
        );
    }

    public function test_a_failed_package_returns_to_endorsed_with_the_error_recorded(): void
    {
        $names = ['Ana Cruz', 'Ben Diaz'];
        $endorsement = $this->makeEndorsement($names);

        $this->failGenerationAt('Ben Diaz');
        $this->generateExpectingFailure($endorsement, $names);

        $endorsement->refresh();

        $this->assertSame(CertificateEndorsement::STATUS_ENDORSED, $endorsement->status);
        $this->assertNotNull($endorsement->generation_error);
        $this->assertStringContainsString('Injected generation failure', $endorsement->generation_error);
        $this->assertNull($endorsement->generation_batch_id);
        $this->assertNull($endorsement->rd_approved_at);
        $this->assertSame(0, (int) $endorsement->generated_count);
    }

    public function test_no_emails_are_sent_when_generation_fails(): void
    {
        Mail::fake();

        $names = ['Ana Cruz', 'Ben Diaz'];
        $endorsement = $this->makeEndorsement($names);

        $this->failGenerationAt('Ben Diaz');
        $this->generateExpectingFailure($endorsement, $names);

        Mail::assertNothingSent();
    }

    public function test_the_normalized_template_is_removed_after_a_failure(): void
    {
        $names = ['Ana Cruz', 'Ben Diaz'];
        $endorsement = $this->makeEndorsement($names);

        $this->failGenerationAt('Ben Diaz');
        $this->generateExpectingFailure($endorsement, $names);

        $this->assertEmpty(
            Storage::disk('local')->files(EndorsementGenerationCoordinator::TEMPLATE_DIR),
            'The batch must delete its normalized template in finally() on failure too.'
        );
    }

    public function test_a_rolled_back_package_regenerates_completely_on_re_approval(): void
    {
        $names = ['Ana Cruz', 'Ben Diaz', 'Cara Evasco'];
        $endorsement = $this->makeEndorsement($names);

        $this->failGenerationAt('Ben Diaz');
        $this->generateExpectingFailure($endorsement, $names);

        // The RD clicks Approve again; there is no separate retry action.
        $this->failGenerationAt('');
        $this->generate($endorsement->fresh(), $names);

        $endorsement->refresh();
        $this->assertSame(CertificateEndorsement::STATUS_RD_APPROVED, $endorsement->status);
        $this->assertSame(3, $endorsement->generated_count);

        $certificates = Certificate::where('endorsement_id', $endorsement->id)
            ->orderBy('endorsement_participant_index')
            ->get();

        $this->assertCount(3, $certificates);
        $this->assertSame($names, $certificates->pluck('participant_name')->all());

        foreach ($certificates as $certificate) {
            $this->assertTrue(Storage::disk('local')->exists($certificate->stamped_pdf_path));
        }

        $allCodes = Certificate::pluck('certificate_code');
        $this->assertSame(
            $allCodes->count(),
            $allCodes->unique()->count(),
            'A rollback-then-retry must not collide with the codes the failed run consumed.'
        );
    }

    public function test_rollback_refuses_to_run_without_an_endorsement_id(): void
    {
        $historic = $this->seedHistoricCertificate('2026-FOD-CIW-99-001', '2026-03-01 08:00:00');

        app(EndorsementGenerationCoordinator::class)->rollback(0, 'no id');

        $this->assertDatabaseHas('certificates', ['id' => $historic->id]);
    }

    private function failGenerationAt(string $participantName): void
    {
        FailingCertificateGenerationService::$failOnParticipantName = $participantName;
    }

    /**
     * @return array<int, string>
     */
    private function pathsWrittenBeforeFailure(): array
    {
        return FailingCertificateGenerationService::$writtenPaths;
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

    /**
     * @param  array<int, string>  $names
     */
    private function generateExpectingFailure(CertificateEndorsement $endorsement, array $names): void
    {
        $coordinator = app(EndorsementGenerationCoordinator::class);
        $this->assertTrue($coordinator->claim($endorsement, null));
        $coordinator->dispatchGeneration($endorsement, $this->participants($names), null);
        $this->runQueueWorker();
    }
}
