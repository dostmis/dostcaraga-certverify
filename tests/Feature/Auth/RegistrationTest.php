<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_register_as_pending_and_are_not_logged_in(): void
    {
        // Registration in this app creates an endorser account that must be
        // approved by the Regional Director before it can log in. It requires
        // a username and a role, and does NOT authenticate the user.
        $response = $this->post('/register', [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'role' => User::ROLE_ORGANIZER,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'username' => 'testuser',
            'role' => User::ROLE_ORGANIZER,
            'approval_status' => 'pending',
            'is_admin' => false,
        ]);
    }

    public function test_registration_rejects_a_self_assigned_regional_director_role(): void
    {
        // Self-registration must not be able to grant the Regional Director role.
        $response = $this->post('/register', [
            'name' => 'Sneaky User',
            'username' => 'sneaky',
            'email' => 'sneaky@example.com',
            'role' => User::ROLE_REGIONAL_DIRECTOR,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }
}
