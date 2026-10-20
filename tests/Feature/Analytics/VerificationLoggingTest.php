<?php

namespace Tests\Feature\Analytics;

use App\Models\Certificate;
use App\Models\CertificateVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VerificationLoggingTest extends TestCase
{
    use RefreshDatabase;

    private function certificate(string $status = 'valid'): Certificate
    {
        return Certificate::create([
            'certificate_code' => 'CERT-'.Str::upper(Str::random(8)),
            'participant_name' => 'Juan Dela Cruz',
            'training_title' => 'Food Safety Orientation',
            'training_date' => '2026-03-14',
            'issuing_office' => 'DOST Caraga',
            'status' => $status,
        ]);
    }

    public function test_a_valid_lookup_is_logged_against_the_certificate(): void
    {
        $cert = $this->certificate();

        $this->get(route('cert.verify', ['t' => $cert->public_token]))->assertOk();

        $row = CertificateVerification::sole();
        $this->assertSame($cert->id, $row->certificate_id);
        $this->assertSame(CertificateVerification::OUTCOME_VALID, $row->outcome);
        $this->assertNotNull($row->created_at);
    }

    public function test_a_revoked_certificate_lookup_is_logged_as_revoked_not_valid(): void
    {
        $cert = $this->certificate('revoked');

        $this->get(route('cert.verify', ['t' => $cert->public_token]))->assertOk();

        $this->assertSame(CertificateVerification::OUTCOME_REVOKED, CertificateVerification::sole()->outcome);
    }

    public function test_any_other_status_is_logged_as_invalid(): void
    {
        $cert = $this->certificate('expired');

        $this->get(route('cert.verify', ['t' => $cert->public_token]))->assertOk();

        $this->assertSame(CertificateVerification::OUTCOME_INVALID, CertificateVerification::sole()->outcome);
    }

    public function test_an_unknown_but_well_formed_token_is_logged_as_not_found(): void
    {
        $this->get(route('cert.verify', ['t' => (string) Str::uuid()]))->assertOk();

        $row = CertificateVerification::sole();
        $this->assertNull($row->certificate_id);
        $this->assertSame(CertificateVerification::OUTCOME_NOT_FOUND, $row->outcome);
    }

    public function test_missing_and_non_uuid_tokens_are_logged_as_malformed(): void
    {
        $this->get(route('cert.verify'))->assertOk();
        $this->get(route('cert.verify', ['t' => 'not-a-uuid']))->assertOk();

        $this->assertSame(2, CertificateVerification::count());
        $this->assertSame(
            [CertificateVerification::OUTCOME_MALFORMED, CertificateVerification::OUTCOME_MALFORMED],
            CertificateVerification::pluck('outcome')->all()
        );
    }

    /**
     * The privacy guarantee the feature was approved on: the raw address never
     * reaches the database, and the digest is peppered rather than a plain
     * hash of the IP.
     */
    public function test_the_raw_ip_is_never_stored_and_the_hash_is_peppered(): void
    {
        $cert = $this->certificate();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->get(route('cert.verify', ['t' => $cert->public_token]))->assertOk();

        $row = CertificateVerification::sole();

        $this->assertNotNull($row->ip_hash);
        $this->assertSame(64, strlen($row->ip_hash));
        $this->assertStringNotContainsString('203.0.113.42', json_encode($row->getAttributes()));
        $this->assertNotSame(hash('sha256', '203.0.113.42'), $row->ip_hash);
    }

    public function test_no_user_agent_string_is_stored_only_a_coarse_class(): void
    {
        $cert = $this->certificate();
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15';

        $this->withHeaders(['User-Agent' => $ua])
            ->get(route('cert.verify', ['t' => $cert->public_token]))->assertOk();

        $row = CertificateVerification::sole();
        $this->assertSame('mobile', $row->ua_class);
        $this->assertStringNotContainsString('AppleWebKit', json_encode($row->getAttributes()));
    }

    public function test_crawlers_are_classed_as_bots_even_when_claiming_to_be_mobile(): void
    {
        $cert = $this->certificate();

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 10) Googlebot/2.1'])
            ->get(route('cert.verify', ['t' => $cert->public_token]))->assertOk();

        $this->assertSame('bot', CertificateVerification::sole()->ua_class);
    }

    public function test_only_the_referer_host_is_stored_never_the_full_url(): void
    {
        $cert = $this->certificate();

        $this->withHeaders(['Referer' => 'https://hr.example.com/checks?t=secret-token-value'])
            ->get(route('cert.verify', ['t' => $cert->public_token]))->assertOk();

        $row = CertificateVerification::sole();
        $this->assertSame('hr.example.com', $row->referer_host);
        $this->assertStringNotContainsString('secret-token-value', json_encode($row->getAttributes()));
    }

    public function test_verification_still_succeeds_when_logging_fails(): void
    {
        $cert = $this->certificate();

        // Drop the log table to simulate a write failure. Verification is the
        // user-facing promise; analytics must never be able to break it.
        \Illuminate\Support\Facades\Schema::drop('certificate_verifications');

        $this->get(route('cert.verify', ['t' => $cert->public_token]))
            ->assertOk()
            ->assertSee($cert->certificate_code);
    }

    public function test_prune_command_deletes_only_rows_past_the_retention_window(): void
    {
        $cert = $this->certificate();

        $old = CertificateVerification::create(['certificate_id' => $cert->id, 'outcome' => 'valid']);
        $old->forceFill(['created_at' => now()->subMonths(30)])->save();

        $recent = CertificateVerification::create(['certificate_id' => $cert->id, 'outcome' => 'valid']);

        $this->artisan('analytics:prune-verifications')->assertSuccessful();

        $this->assertDatabaseMissing('certificate_verifications', ['id' => $old->id]);
        $this->assertDatabaseHas('certificate_verifications', ['id' => $recent->id]);
    }
}
