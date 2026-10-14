<?php

namespace Tests\Feature\Auth;

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

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'role' => \App\Models\User::ROLE_ORGANIZER,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // New registrations are created pending RD approval and are NOT
        // logged in; the user is sent to the login screen.
        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'username' => 'testuser',
            'approval_status' => 'pending',
            'role' => \App\Models\User::ROLE_ORGANIZER,
        ]);
    }
}
