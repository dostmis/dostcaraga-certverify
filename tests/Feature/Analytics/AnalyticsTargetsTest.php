<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsTarget;
use App\Models\User;
use App\Services\CertificateGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTargetsTest extends TestCase
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

    private function rd(): User
    {
        return $this->staff(User::ROLE_REGIONAL_DIRECTOR, true);
    }

    public function test_only_the_regional_director_can_reach_the_targets_screen(): void
    {
        $this->actingAs($this->rd())->get(route('admin.analytics.targets.edit'))->assertOk();

        foreach ([User::ROLE_UNIT_SUPERVISOR, User::ROLE_ORGANIZER] as $role) {
            $this->actingAs($this->staff($role))
                ->get(route('admin.analytics.targets.edit'))
                ->assertForbidden();
        }
    }

    public function test_the_targets_screen_requires_authentication(): void
    {
        $this->get(route('admin.analytics.targets.edit'))->assertRedirect(route('login'));
    }

    public function test_non_rd_cannot_save_targets(): void
    {
        $this->actingAs($this->staff(User::ROLE_ORGANIZER))
            ->post(route('admin.analytics.targets.update'), [
                'period_year' => 2026,
                'rows' => [['program_code' => 'SETUP', 'target_certificates' => 100]],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('analytics_targets', 0);
    }

    public function test_the_screen_lists_every_canonical_program(): void
    {
        $response = $this->actingAs($this->rd())->get(route('admin.analytics.targets.edit'))->assertOk();

        foreach ((new CertificateGenerationService)->dostPrograms() as $program) {
            if ($program === 'Others') {
                continue;
            }
            $response->assertSee($program, escape: false);
        }
    }

    public function test_rd_can_save_targets_per_program(): void
    {
        $this->actingAs($this->rd())
            ->post(route('admin.analytics.targets.update'), [
                'period_year' => 2026,
                'rows' => [
                    ['program_code' => 'SETUP', 'target_certificates' => 800, 'target_people' => 700],
                    ['program_code' => 'CEST', 'target_certificates' => 600, 'target_people' => null],
                ],
            ])
            ->assertRedirect(route('admin.analytics.targets.edit', ['year' => 2026]))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('analytics_targets', [
            'program_code' => 'SETUP',
            'period_year' => 2026,
            'province_code' => 0,
            'target_certificates' => 800,
            'target_people' => 700,
        ]);

        $cest = AnalyticsTarget::where('program_code', 'CEST')->sole();
        $this->assertSame(600, $cest->target_certificates);
        // A blank input means "no target", which is not the same as zero.
        $this->assertNull($cest->target_people);
    }

    public function test_saving_twice_updates_rather_than_duplicates(): void
    {
        $rd = $this->rd();
        $payload = fn (int $value) => [
            'period_year' => 2026,
            'rows' => [['program_code' => 'SETUP', 'target_certificates' => $value]],
        ];

        $this->actingAs($rd)->post(route('admin.analytics.targets.update'), $payload(800));
        $this->actingAs($rd)->post(route('admin.analytics.targets.update'), $payload(950));

        $this->assertDatabaseCount('analytics_targets', 1);
        $this->assertSame(950, AnalyticsTarget::sole()->target_certificates);
    }

    public function test_an_all_blank_row_clears_a_saved_target(): void
    {
        $rd = $this->rd();

        $this->actingAs($rd)->post(route('admin.analytics.targets.update'), [
            'period_year' => 2026,
            'rows' => [['program_code' => 'SETUP', 'target_certificates' => 800]],
        ]);
        $this->assertDatabaseCount('analytics_targets', 1);

        $this->actingAs($rd)->post(route('admin.analytics.targets.update'), [
            'period_year' => 2026,
            'rows' => [['program_code' => 'SETUP', 'target_certificates' => '', 'target_people' => '', 'notes' => '']],
        ]);

        $this->assertDatabaseCount('analytics_targets', 0);
    }

    public function test_targets_are_scoped_per_year(): void
    {
        $rd = $this->rd();

        foreach ([2026, 2027] as $year) {
            $this->actingAs($rd)->post(route('admin.analytics.targets.update'), [
                'period_year' => $year,
                'rows' => [['program_code' => 'SETUP', 'target_certificates' => $year]],
            ]);
        }

        $this->assertDatabaseCount('analytics_targets', 2);
        $this->assertSame(2027, AnalyticsTarget::query()->forYear(2027)->sole()->target_certificates);
    }

    public function test_negative_targets_are_rejected(): void
    {
        $this->actingAs($this->rd())
            ->post(route('admin.analytics.targets.update'), [
                'period_year' => 2026,
                'rows' => [['program_code' => 'SETUP', 'target_certificates' => -5]],
            ])
            ->assertSessionHasErrors('rows.0.target_certificates');

        $this->assertDatabaseCount('analytics_targets', 0);
    }

    public function test_a_program_with_no_certificates_yet_can_still_be_targeted(): void
    {
        $rd = $this->rd();

        // The normal case: targets are set before the first certificate exists.
        $this->actingAs($rd)->post(route('admin.analytics.targets.update'), [
            'period_year' => 2026,
            'rows' => [['program_code' => 'Brand New Program 2026', 'target_certificates' => 120]],
        ]);

        // And it must then appear on its own form, not vanish.
        $this->actingAs($rd)
            ->get(route('admin.analytics.targets.edit', ['year' => 2026]))
            ->assertOk()
            ->assertSee('Brand New Program 2026');
    }

    public function test_prefill_from_a_previous_year_does_not_save_anything(): void
    {
        $rd = $this->rd();

        $this->actingAs($rd)->post(route('admin.analytics.targets.update'), [
            'period_year' => 2026,
            'rows' => [['program_code' => 'SETUP', 'target_certificates' => 800]],
        ]);

        $this->actingAs($rd)
            ->get(route('admin.analytics.targets.edit', ['year' => 2027, 'copy_from' => 2026]))
            ->assertOk()
            ->assertSee('value="800"', escape: false);

        // Still only the 2026 row: a prefill is a preview, not a write.
        $this->assertDatabaseCount('analytics_targets', 1);
        $this->assertSame(2026, AnalyticsTarget::sole()->period_year);
    }

    public function test_the_dashboard_payload_carries_targets_with_a_pacing_verdict(): void
    {
        $rd = $this->rd();
        $year = (int) now()->year;

        $this->actingAs($rd)->post(route('admin.analytics.targets.update'), [
            'period_year' => $year,
            'rows' => [['program_code' => 'SETUP', 'target_certificates' => 1000]],
        ]);

        $payload = $this->actingAs($rd)->getJson(route('admin.analytics.data'))
            ->assertOk()
            ->json();

        $this->assertTrue($payload['targets']['has_targets']);
        $this->assertSame($year, $payload['targets']['year']);
        $this->assertSame('SETUP', $payload['targets']['programs'][0]['program_code']);
        $this->assertSame(1000, $payload['targets']['programs'][0]['certificates']['target']);
        $this->assertArrayHasKey('status', $payload['targets']['programs'][0]['certificates']);
    }

    /**
     * Targets are region-wide and annual, so the Pace block must report which
     * active filters it is ignoring rather than comparing a filtered actual
     * against an unfiltered target.
     */
    public function test_the_payload_reports_filters_the_targets_block_ignores(): void
    {
        $payload = $this->actingAs($this->rd())
            ->getJson(route('admin.analytics.data', ['gender' => 'Female', 'age_band' => '25-34']))
            ->assertOk()
            ->json();

        $this->assertEqualsCanonicalizing(['gender', 'age_band'], $payload['targets']['ignored_filters']);
    }
}
