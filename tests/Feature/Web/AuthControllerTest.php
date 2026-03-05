<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── show pages ───────────────────────────────────────────────────────────

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_register_page_renders(): void
    {
        $this->get('/register')->assertOk();
    }

    // ─── login ────────────────────────────────────────────────────────────────

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/communities');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_login_requires_email(): void
    {
        $this->post('/login', ['password' => 'password'])
            ->assertSessionHasErrors(['email']);
    }

    public function test_login_requires_password(): void
    {
        $this->post('/login', ['email' => 'test@example.com'])
            ->assertSessionHasErrors(['password']);
    }

    // ─── register ─────────────────────────────────────────────────────────────

    public function test_user_can_register(): void
    {
        $this->post('/register', [
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'email'                 => 'john@example.com',
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect('/communities');

        $this->assertDatabaseHas('users', ['email' => 'john@example.com', 'name' => 'John Doe']);
    }

    public function test_user_is_auto_logged_in_after_register(): void
    {
        $this->post('/register', [
            'first_name'            => 'Jane',
            'last_name'             => 'Smith',
            'email'                 => 'jane@example.com',
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $this->assertAuthenticated();
    }

    public function test_register_generates_username(): void
    {
        $this->post('/register', [
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'email'                 => 'john@example.com',
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertStringStartsWith('john-doe-', $user->username);
        $this->assertStringEndsWith((string) $user->id, $user->username);
    }

    public function test_register_requires_first_name(): void
    {
        $this->post('/register', [
            'last_name'             => 'Doe',
            'email'                 => 'john@example.com',
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertSessionHasErrors(['first_name']);
    }

    public function test_register_requires_last_name(): void
    {
        $this->post('/register', [
            'first_name'            => 'John',
            'email'                 => 'john@example.com',
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertSessionHasErrors(['last_name']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', [
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'email'                 => 'taken@example.com',
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertSessionHasErrors(['email']);
    }

    public function test_register_requires_password_confirmation(): void
    {
        $this->post('/register', [
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'email'                 => 'john@example.com',
            'password'              => 'Password1!',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['password']);
    }

    // ─── logout ───────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }
}
