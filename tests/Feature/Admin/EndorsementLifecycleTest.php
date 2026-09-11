<?php

namespace Tests\Feature\Admin;

use App\Models\CertificateEndorsement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Characterization tests for the certificate endorsement approval spine:
 * endorse -> (RD) approve / reject. These pin down the state machine and
 * authorization rules WITHOUT exercising PDF generation, so they stay fast
 * and non-fragile while guarding the reject-with-reason behaviour.
 */
class EndorsementLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function regionalDirector(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_REGIONAL_DIRECTOR,
            'is_admin' => true,
        ]);
    }

    private function organizer(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ORGANIZER,
            'is_admin' => false,
        ]);
    }

    private function endorsement(array $overrides = []): CertificateEndorsement
    {
        return CertificateEndorsement::create(array_merge([
            'status' => CertificateEndorsement::STATUS_ENDORSED,
            'submitted_by' => $this->organizer()->id,
            'participants_count' => 3,
            'participants_file_path' => 'endorsements/participants/test.json',
            'template_pdf_path' => 'endorsements/templates/test.pdf',
            'payload' => ['training_title' => 'Test Training'],
        ], $overrides));
    }

    public function test_reject_requires_a_reason(): void
    {
        $rd = $this->regionalDirector();
        $endorsement = $this->endorsement();

        $response = $this->actingAs($rd)->post(
            route('admin.certs.endorsements.reject', ['id' => $endorsement->id]),
            [] // no rejection_reason
        );

        $response->assertSessionHasErrors('rejection_reason');

        $endorsement->refresh();
        $this->assertSame(CertificateEndorsement::STATUS_ENDORSED, $endorsement->status);
        $this->assertNull($endorsement->rejection_reason);
    }

    public function test_rd_can_reject_an_endorsed_package_with_a_reason(): void
    {
        $rd = $this->regionalDirector();
        $endorsement = $this->endorsement();

        $response = $this->actingAs($rd)->post(
            route('admin.certs.endorsements.reject', ['id' => $endorsement->id]),
            ['rejection_reason' => 'Incomplete participant list.']
        );

        $response->assertSessionHasNoErrors();

        $endorsement->refresh();
        $this->assertSame(CertificateEndorsement::STATUS_RD_REJECTED, $endorsement->status);
        $this->assertSame('Incomplete participant list.', $endorsement->rejection_reason);
        $this->assertSame($rd->id, $endorsement->rd_rejected_by);
        $this->assertNotNull($endorsement->rd_rejected_at);
    }

    public function test_only_endorsed_packages_can_be_rejected(): void
    {
        $rd = $this->regionalDirector();
        $endorsement = $this->endorsement([
            'status' => CertificateEndorsement::STATUS_RD_APPROVED,
            'generated_count' => 3,
        ]);

        $this->actingAs($rd)->post(
            route('admin.certs.endorsements.reject', ['id' => $endorsement->id]),
            ['rejection_reason' => 'Trying to reject an already-approved package.']
        );

        $endorsement->refresh();
        // Status is unchanged and no reason is written for a non-endorsed package.
        $this->assertSame(CertificateEndorsement::STATUS_RD_APPROVED, $endorsement->status);
        $this->assertNull($endorsement->rejection_reason);
    }

    public function test_non_regional_director_cannot_reject(): void
    {
        $organizer = $this->organizer();
        $endorsement = $this->endorsement();

        $response = $this->actingAs($organizer)->post(
            route('admin.certs.endorsements.reject', ['id' => $endorsement->id]),
            ['rejection_reason' => 'I am not allowed to do this.']
        );

        $response->assertForbidden();

        $endorsement->refresh();
        $this->assertSame(CertificateEndorsement::STATUS_ENDORSED, $endorsement->status);
    }

    public function test_approve_is_blocked_until_participants_are_reviewed(): void
    {
        $rd = $this->regionalDirector();
        $endorsement = $this->endorsement();

        // Approving without first reviewing participants (no session flag set)
        // must NOT generate certificates or advance the status.
        $response = $this->actingAs($rd)->post(
            route('admin.certs.endorsements.approve', ['id' => $endorsement->id])
        );

        $response->assertSessionHasErrors();

        $endorsement->refresh();
        $this->assertSame(CertificateEndorsement::STATUS_ENDORSED, $endorsement->status);
        $this->assertNull($endorsement->generated_count);
    }

    public function test_non_regional_director_cannot_approve(): void
    {
        $organizer = $this->organizer();
        $endorsement = $this->endorsement();

        $response = $this->actingAs($organizer)->post(
            route('admin.certs.endorsements.approve', ['id' => $endorsement->id])
        );

        $response->assertForbidden();

        $endorsement->refresh();
        $this->assertSame(CertificateEndorsement::STATUS_ENDORSED, $endorsement->status);
    }
}
