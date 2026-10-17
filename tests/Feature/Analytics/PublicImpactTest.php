<?php

namespace Tests\Feature\Analytics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicImpactTest extends TestCase
{
    use RefreshDatabase;

    /** Keys that must NEVER appear in the public reach feed. */
    private const FORBIDDEN_KEYS = [
        'name', 'participant_name', 'first_name', 'last_name', 'email',
        'contact_number', 'birthdate', 'barangay', 'barangay_name',
        'person_key', 'recipient_id', 'certificate_code', 'public_token',
    ];

    public function test_impact_page_is_public(): void
    {
        $this->get(route('impact.index'))->assertOk();
    }

    public function test_public_data_feed_is_pii_free_and_has_no_barangay(): void
    {
        $res = $this->getJson(route('impact.data'))->assertOk()
            ->assertJsonStructure(['available', 'generated_at', 'reach', 'activity']);

        // No forbidden key may appear in any reach cell or activity row.
        foreach ([$res->json('reach'), $res->json('activity')] as $rows) {
            $this->assertIsArray($rows);
            foreach ($rows as $row) {
                foreach (self::FORBIDDEN_KEYS as $key) {
                    $this->assertArrayNotHasKey($key, $row, "Public feed leaked key: {$key}");
                }
            }
        }

        // The journey funnel is aggregate counts only (no identifying keys).
        $funnel = $res->json('funnel');
        if (is_array($funnel)) {
            foreach (array_keys($funnel) as $key) {
                $this->assertNotContains($key, self::FORBIDDEN_KEYS, "Funnel leaked key: {$key}");
            }
        }
    }
}
