<?php

namespace Tests\Feature\Analytics;

use App\Jobs\RefreshAnalyticsJob;
use App\Models\Certificate;
use App\Services\EndorsementGenerationCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Feature\Admin\Concerns\GeneratesCertificatePackages;
use Tests\TestCase;

/**
 * The analytics refresh must never ride the generation rollback path. It is
 * dispatched only from finalizeSuccess() (the success callback), so a rolled
 * back package must trigger no RefreshAnalyticsJob.
 */
class AnalyticsRollbackIsolationTest extends TestCase
{
    use GeneratesCertificatePackages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareGenerationEnvironment();
    }

    public function test_rollback_does_not_dispatch_an_analytics_refresh(): void
    {
        Bus::fake();

        $endorsement = $this->makeEndorsement(['Ana Cruz', 'Ben Diaz']);

        // Simulate certificates created by a run that then fails mid-batch.
        foreach (['2026-DEMO-AAA-01-001', '2026-DEMO-AAA-01-002'] as $i => $code) {
            Certificate::create([
                'certificate_code' => $code,
                'participant_name' => "Person {$i}",
                'training_title' => 'Demo',
                'training_date' => '2026-03-01',
                'issuing_office' => 'DOST Caraga',
                'status' => 'valid',
                'endorsement_id' => $endorsement->id,
                'endorsement_participant_index' => $i,
            ]);
        }

        app(EndorsementGenerationCoordinator::class)->rollback($endorsement->id, 'Simulated failure');

        // Rollback happened…
        $this->assertSame(0, Certificate::where('endorsement_id', $endorsement->id)->count());
        // …and it did NOT trigger an analytics refresh.
        Bus::assertNotDispatched(RefreshAnalyticsJob::class);
    }
}
