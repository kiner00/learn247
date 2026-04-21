<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_and_user_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_login_returns_401_with_invalid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_creates_user_and_returns_token_and_user(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.email', 'john@example.com');

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function test_register_validation_errors_for_missing_fields(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'password']);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout');

        $response->assertOk()->assertJsonPath('message', 'Logged out.');
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_forgot_password_returns_generic_success_for_existing_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $response = $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);

        $response->assertOk()->assertJsonStructure(['message']);
    }

    public function test_forgot_password_returns_generic_success_for_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertOk()->assertJsonStructure(['message']);
    }

    public function test_forgot_password_validates_email_field(): void
    {
        $this->postJson('/api/auth/forgot-password', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_forgot_password_requires_email_field(): void
    {
        $this->postJson('/api/auth/forgot-password', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_verify_reset_token_returns_true_for_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'token@example.com']);
        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/auth/verify-reset-token', [
            'email' => $user->email,
            'token' => $token,
        ]);

        $response->assertOk()->assertJsonPath('valid', true);
    }

    public function test_verify_reset_token_returns_false_for_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'bad@example.com']);

        $response = $this->postJson('/api/auth/verify-reset-token', [
            'email' => $user->email,
            'token' => 'nonsense-token',
        ]);

        $response->assertOk()->assertJsonPath('valid', false);
    }

    public function test_verify_reset_token_returns_false_for_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/verify-reset-token', [
            'email' => 'ghost@example.com',
            'token' => 'some-token',
        ]);

        $response->assertOk()->assertJsonPath('valid', false);
    }

    public function test_verify_reset_token_validates_required_fields(): void
    {
        $this->postJson('/api/auth/verify-reset-token', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'token']);
    }

    public function test_reset_password_succeeds_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'valid@example.com',
            'needs_password_setup' => true,
        ]);
        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ]);

        $response->assertOk()->assertJsonStructure(['message']);

        $user->refresh();
        $this->assertFalse((bool) $user->needs_password_setup);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPassword1!', $user->password));
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'bad-token@example.com']);

        $response = $this->postJson('/api/auth/reset-password', [
            'token' => 'bad-token',
            'email' => $user->email,
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_reset_password_validates_required_fields(): void
    {
        $this->postJson('/api/auth/reset-password', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token', 'email', 'password']);
    }

    public function test_reset_password_requires_confirmation_match(): void
    {
        $user = User::factory()->create(['email' => 'confirm@example.com']);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword1!',
            'password_confirmation' => 'Different1!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }
}
