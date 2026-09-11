<?php

namespace Tests\Feature;

use App\Jobs\AnchorCertificateOnHederaJob;
use App\Models\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CertificateVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_certificate_statuses_are_verified_locally_with_legacy_anchor_data(): void
    {
        Http::preventStrayRequests();
        config(['services.hedera.enabled' => true]);

        foreach (['valid', 'invalid', 'revoked'] as $status) {
            $cert = Certificate::create([
                'certificate_code' => 'TEST-'.$status,
                'participant_name' => 'Test Recipient',
                'training_title' => 'Test Training',
                'training_date' => '2026-06-01',
                'issuing_office' => 'DOST Caraga',
                'status' => $status,
            ]);
            $cert->forceFill([
                'blockchain_status' => 'anchored',
                'blockchain_topic_id' => '0.0.1234',
                'blockchain_sequence_number' => 1,
            ])->save();

            $this->get(route('cert.verify', ['t' => $cert->public_token]))
                ->assertOk()
                ->assertViewHas('found', true)
                ->assertViewHas('cert', fn ($record) => $record->status === $status)
                ->assertSee('TEST-'.$status)
                ->assertDontSee('chainPanel')
                ->assertDontSee('HashScan');
        }

        Http::assertNothingSent();
    }

    public function test_missing_and_unknown_tokens_still_show_verification_results(): void
    {
        $this->get(route('cert.verify'))->assertOk()
            ->assertViewHas('found', false)->assertViewHas('reason', 'Missing token.');
        $this->get(route('cert.verify', ['t' => '00000000-0000-4000-8000-000000000001']))->assertOk()
            ->assertViewHas('found', false)->assertViewHas('reason', 'Token not found.');
    }

    public function test_previously_queued_anchor_jobs_complete_without_external_requests(): void
    {
        Http::preventStrayRequests();
        $job = unserialize(serialize(new AnchorCertificateOnHederaJob(123)));

        app()->call([$job, 'handle']);

        Http::assertNothingSent();
    }
}
