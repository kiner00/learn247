<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ForgotPasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_renders_forgot_password_page(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_send_returns_success_regardless_of_email_existence(): void
    {
        $this->post('/forgot-password', ['email' => 'nonexistent@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_send_sends_reset_link_for_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'real@example.com']);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_send_validates_email_field(): void
    {
        $this->post('/forgot-password', ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    }

    public function test_show_reset_renders_reset_password_page(): void
    {
        $this->get('/reset-password/some-token?email=user@example.com')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/ResetPassword')
                ->where('token', 'some-token')
                ->where('email', 'user@example.com')
            );
    }

    public function test_reset_with_invalid_token_returns_error(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->post('/reset-password', [
            'token' => 'bad-token',
            'email' => $user->email,
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertSessionHasErrors('email');
    }

    public function test_reset_with_valid_token_updates_password(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com', 'needs_password_setup' => true]);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertFalse((bool) $user->needs_password_setup);
    }

    public function test_reset_validates_required_fields(): void
    {
        $this->post('/reset-password', [])
            ->assertSessionHasErrors(['token', 'email', 'password']);
    }
}
