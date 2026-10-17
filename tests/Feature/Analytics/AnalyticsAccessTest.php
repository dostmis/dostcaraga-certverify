<?php

namespace Tests\Feature\Analytics;

use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsAccessTest extends TestCase
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

    public function test_all_staff_roles_land_on_the_new_dashboard(): void
    {
        foreach ([
            [User::ROLE_REGIONAL_DIRECTOR, true],
            [User::ROLE_UNIT_SUPERVISOR, false],
            [User::ROLE_ORGANIZER, false],
        ] as [$role, $admin]) {
            $user = $this->staff($role, $admin);

            $this->actingAs($user)->get('/dashboard')
                ->assertRedirect(route('admin.dashboard', absolute: false));

            $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
        }
    }

    public function test_legacy_analytics_route_redirects_to_dashboard(): void
    {
        $user = $this->staff(User::ROLE_ORGANIZER);

        $this->actingAs($user)->get(route('admin.analytics.index'))
            ->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_dashboard_and_data_require_authentication(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.analytics.data'))->assertRedirect(route('login'));
    }

    public function test_data_endpoint_returns_json_for_each_staff_role(): void
    {
        foreach ([User::ROLE_REGIONAL_DIRECTOR, User::ROLE_UNIT_SUPERVISOR, User::ROLE_ORGANIZER] as $role) {
            $user = $this->staff($role, $role === User::ROLE_REGIONAL_DIRECTOR);

            $this->actingAs($user)->getJson(route('admin.analytics.data'))
                ->assertOk()
                ->assertJsonStructure(['available', 'filters', 'executive', 'operations']);
        }
    }

    public function test_filter_validation_rejects_unknown_and_invalid_values(): void
    {
        $user = $this->staff(User::ROLE_REGIONAL_DIRECTOR, true);

        $this->actingAs($user)->getJson(route('admin.analytics.data', ['foo' => 'bar']))
            ->assertStatus(422)->assertJsonValidationErrors('filters');

        $this->actingAs($user)->getJson(route('admin.analytics.data', ['gender' => 'Alien']))
            ->assertStatus(422)->assertJsonValidationErrors('gender');

        $this->actingAs($user)->getJson(route('admin.analytics.data', ['age_band' => '99+']))
            ->assertStatus(422)->assertJsonValidationErrors('age_band');

        // Valid filters pass.
        $this->actingAs($user)->getJson(route('admin.analytics.data', ['gender' => 'Male', 'program' => 'SETUP']))
            ->assertOk();
    }

    public function test_recipient_guard_is_unaffected(): void
    {
        $recipient = Recipient::create([
            'name' => 'Repo User',
            'email' => 'repo.user@example.test',
            'password' => bcrypt('password123'),
        ]);

        // Recipient dashboard still works on its own guard.
        $this->actingAs($recipient, 'recipient')->get(route('recipient.certificates'))->assertOk();

        // The recipient guard is separate from the staff web guard: a logged-in
        // recipient is not a web user, so the admin dashboard is never theirs.
        $this->assertTrue(auth('recipient')->check());
        $this->assertFalse(auth('web')->check());
    }
}
