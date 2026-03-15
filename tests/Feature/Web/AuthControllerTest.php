<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_returns_200(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/communities');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_register_page_returns_200(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
    }

    public function test_user_can_register(): void
    {
        $response = $this->post('/register', [
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/communities');
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
        $this->assertAuthenticated();
    }

    public function test_register_requires_valid_email(): void
    {
        $response = $this->post('/register', [
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'email'                 => 'not-a-valid-email',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_stores_intended_url_from_redirect_query(): void
    {
        $response = $this->get('/login?redirect=/communities/test-slug');

        $response->assertOk();
    }

    public function test_register_stores_intended_url_from_redirect_query(): void
    {
        $response = $this->get('/register?redirect=/communities/test-slug');

        $response->assertOk();
    }
}
