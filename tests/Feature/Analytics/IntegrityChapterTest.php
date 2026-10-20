<?php

namespace Tests\Feature\Analytics;

use App\Models\Certificate;
use App\Models\CertificateVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IntegrityChapterTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, bool $admin = false): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_admin' => $admin,
            'approval_status' => 'approved',
        ]);
    }

    private function certificate(string $status = 'valid'): Certificate
    {
        return Certificate::create([
            'certificate_code' => 'CERT-'.Str::upper(Str::random(8)),
            'participant_name' => 'Maria Santos',
            'training_title' => 'Metrology Basics',
            'training_date' => '2026-05-02',
            'issuing_office' => 'DOST Caraga',
            'status' => $status,
        ]);
    }

    public function test_integrity_is_present_for_the_rd_and_absent_for_everyone_else(): void
    {
        $rd = $this->staff(User::ROLE_REGIONAL_DIRECTOR, true);

        $this->actingAs($rd)->getJson(route('admin.analytics.data'))
            ->assertOk()
            ->assertJsonStructure(['integrity' => ['total_attempts', 'failure_rate', 'outcomes', 'bad_hits']]);

        foreach ([User::ROLE_UNIT_SUPERVISOR, User::ROLE_ORGANIZER] as $role) {
            $payload = $this->actingAs($this->staff($role))
                ->getJson(route('admin.analytics.data'))->assertOk()->json();

            $this->assertNull($payload['integrity']);
        }
    }

    public function test_failure_rate_and_bot_share_are_computed_from_the_log(): void
    {
        $cert = $this->certificate();

        // 2 resolved, 2 failed lookups.
        $this->get(route('cert.verify', ['t' => $cert->public_token]));
        $this->get(route('cert.verify', ['t' => $cert->public_token]));
        $this->get(route('cert.verify', ['t' => (string) Str::uuid()]));
        $this->get(route('cert.verify', ['t' => 'garbage']));

        $payload = $this->actingAs($this->staff(User::ROLE_REGIONAL_DIRECTOR, true))
            ->getJson(route('admin.analytics.data'))->assertOk()->json();

        $this->assertSame(4, $payload['integrity']['total_attempts']);
        $this->assertSame(2, $payload['integrity']['failed_attempts']);
        // JSON collapses 50.0 to 50, so compare numerically.
        $this->assertSame(50.0, (float) $payload['integrity']['failure_rate']);
    }

    public function test_revoked_certificate_lookups_surface_as_bad_hits(): void
    {
        $cert = $this->certificate('revoked');

        $this->get(route('cert.verify', ['t' => $cert->public_token]));
        $this->get(route('cert.verify', ['t' => $cert->public_token]));

        $payload = $this->actingAs($this->staff(User::ROLE_REGIONAL_DIRECTOR, true))
            ->getJson(route('admin.analytics.data'))->assertOk()->json();

        $badHits = $payload['integrity']['bad_hits'];
        $this->assertCount(1, $badHits);
        $this->assertSame($cert->certificate_code, $badHits[0]['certificate_code']);
        $this->assertSame(2, (int) $badHits[0]['attempts']);
    }

    public function test_concentrated_lookups_flag_replayed_links(): void
    {
        $cert = $this->certificate();

        // 25 attempts from a single verifier: volume without breadth.
        for ($i = 0; $i < 25; $i++) {
            CertificateVerification::create([
                'certificate_id' => $cert->id,
                'outcome' => 'valid',
                'ip_hash' => str_repeat('a', 64),
            ]);
        }

        $payload = $this->actingAs($this->staff(User::ROLE_REGIONAL_DIRECTOR, true))
            ->getJson(route('admin.analytics.data'))->assertOk()->json();

        $this->assertCount(1, $payload['integrity']['concentrated']);
        $this->assertSame(25, (int) $payload['integrity']['concentrated'][0]['attempts']);
        $this->assertSame(1, (int) $payload['integrity']['concentrated'][0]['unique_verifiers']);
    }

    public function test_broadly_verified_certificates_are_not_flagged_as_concentrated(): void
    {
        $cert = $this->certificate();

        // Same volume, but each from a different verifier — normal usage.
        for ($i = 0; $i < 25; $i++) {
            CertificateVerification::create([
                'certificate_id' => $cert->id,
                'outcome' => 'valid',
                'ip_hash' => hash('sha256', 'verifier-'.$i),
            ]);
        }

        $payload = $this->actingAs($this->staff(User::ROLE_REGIONAL_DIRECTOR, true))
            ->getJson(route('admin.analytics.data'))->assertOk()->json();

        $this->assertCount(0, $payload['integrity']['concentrated']);
    }

    public function test_verification_block_degrades_when_the_views_are_absent(): void
    {
        // SQLite has no materialized views, so the Proof chapter must report
        // itself unavailable rather than erroring.
        $payload = $this->actingAs($this->staff(User::ROLE_ORGANIZER))
            ->getJson(route('admin.analytics.data'))->assertOk()->json();

        $this->assertFalse($payload['available']);
        $this->assertNull($payload['executive']);
        // Targets and integrity read plain tables, so they survive.
        $this->assertArrayHasKey('targets', $payload);
    }
}
