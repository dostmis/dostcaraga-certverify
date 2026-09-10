<?php

namespace Tests\Feature\Admin;

use App\Models\Certificate;
use App\Models\CertificateEndorsement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Admin\Concerns\GeneratesCertificatePackages;
use Tests\TestCase;

/**
 * The approval route itself: it must return immediately and leave the work on
 * the queue, rather than generating in-request.
 */
class CertificateApprovalRequestTest extends TestCase
{
    use GeneratesCertificatePackages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareGenerationEnvironment();
    }

    public function test_approval_returns_immediately_and_leaves_generation_on_the_queue(): void
    {
        $endorsement = $this->makeEndorsement(['Ana Cruz', 'Ben Diaz']);

        $response = $this->actingAs($this->regionalDirector())
            ->withSession([$this->reviewKey($endorsement) => true])
            ->post(route('admin.certs.endorsements.approve', ['id' => $endorsement->id]));

        $response->assertRedirect();
        $response->assertSessionHas('info');

        $endorsement->refresh();
        $this->assertSame(CertificateEndorsement::STATUS_GENERATING, $endorsement->status);
        $this->assertNotNull($endorsement->generation_batch_id);

        // Nothing generated in-request; that is the entire point of the phase.
        $this->assertSame(0, Certificate::count());
        $this->assertSame(2, \Illuminate\Support\Facades\DB::table('jobs')->count());

        $this->runQueueWorker();

        $this->assertSame(CertificateEndorsement::STATUS_RD_APPROVED, $endorsement->fresh()->status);
        $this->assertSame(2, Certificate::where('endorsement_id', $endorsement->id)->count());
    }

    public function test_a_second_approval_of_a_generating_package_dispatches_no_extra_jobs(): void
    {
        $endorsement = $this->makeEndorsement(['Ana Cruz', 'Ben Diaz']);
        $director = $this->regionalDirector();

        $this->actingAs($director)
            ->withSession([$this->reviewKey($endorsement) => true])
            ->post(route('admin.certs.endorsements.approve', ['id' => $endorsement->id]))
            ->assertRedirect();

        $queuedAfterFirst = \Illuminate\Support\Facades\DB::table('jobs')->count();

        $this->actingAs($director)
            ->withSession([$this->reviewKey($endorsement) => true])
            ->post(route('admin.certs.endorsements.approve', ['id' => $endorsement->id]))
            ->assertRedirect();

        $this->assertSame(
            $queuedAfterFirst,
            \Illuminate\Support\Facades\DB::table('jobs')->count(),
            'A double-submitted approval must not dispatch a second batch.'
        );
    }

    public function test_the_progress_endpoint_reports_the_batch_and_requires_the_regional_director(): void
    {
        $endorsement = $this->makeEndorsement(['Ana Cruz', 'Ben Diaz']);

        $this->actingAs($this->regionalDirector())
            ->withSession([$this->reviewKey($endorsement) => true])
            ->post(route('admin.certs.endorsements.approve', ['id' => $endorsement->id]));

        $this->actingAs($this->regionalDirector())
            ->getJson(route('admin.certs.endorsements.generation-progress', ['id' => $endorsement->id]))
            ->assertOk()
            ->assertJson([
                'status' => CertificateEndorsement::STATUS_GENERATING,
                'total' => 2,
                'processed' => 0,
                'finished' => false,
            ]);

        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $this->actingAs($organizer)
            ->get(route('admin.certs.endorsements.generation-progress', ['id' => $endorsement->id]))
            ->assertForbidden();
    }

    public function test_a_generating_package_stays_visible_in_the_approvals_queue(): void
    {
        $endorsement = $this->makeEndorsement(['Ana Cruz']);
        $director = $this->regionalDirector();

        $this->actingAs($director)
            ->withSession([$this->reviewKey($endorsement) => true])
            ->post(route('admin.certs.endorsements.approve', ['id' => $endorsement->id]));

        $this->actingAs($director)
            ->get(route('admin.certs.approvals'))
            ->assertOk()
            ->assertSee('Generating')
            ->assertSee('Community Innovation Workshop');
    }

    /**
     * The preview endpoints render through the same extracted service as
     * issued certificates, so a refactor there must not break them.
     */
    public function test_the_endorsement_preview_still_renders_a_pdf(): void
    {
        $endorsement = $this->makeEndorsement(['Ana Cruz']);

        $response = $this->actingAs($this->regionalDirector())
            ->get(route('admin.certs.endorsements.preview', ['id' => $endorsement->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    private function regionalDirector(): User
    {
        return User::factory()->create(['role' => User::ROLE_REGIONAL_DIRECTOR]);
    }

    private function reviewKey(CertificateEndorsement $endorsement): string
    {
        return 'cert_endorsements.participants_reviewed.' . $endorsement->id;
    }
}
