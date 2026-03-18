<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetPasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_renders_set_password_page(): void
    {
        $user = User::factory()->create(['needs_password_setup' => true]);

        $response = $this->actingAs($user)->get('/set-password');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Auth/SetPassword'));
    }

    public function test_guest_is_redirected_from_show(): void
    {
        $response = $this->get('/set-password');

        $response->assertRedirect('/login');
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_sets_password_and_clears_flag(): void
    {
        $user = User::factory()->create(['needs_password_setup' => true]);

        $response = $this->actingAs($user)
            ->post('/set-password', [
                'password'              => 'NewSecureP@ss1',
                'password_confirmation' => 'NewSecureP@ss1',
            ]);

        $response->assertRedirect('/communities');
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertFalse($user->needs_password_setup);
        $this->assertTrue(Hash::check('NewSecureP@ss1', $user->password));
    }

    public function test_store_requires_password_confirmation(): void
    {
        $user = User::factory()->create(['needs_password_setup' => true]);

        $response = $this->actingAs($user)
            ->post('/set-password', [
                'password'              => 'NewSecureP@ss1',
                'password_confirmation' => 'WrongConfirm',
            ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_store_requires_password(): void
    {
        $user = User::factory()->create(['needs_password_setup' => true]);

        $response = $this->actingAs($user)
            ->post('/set-password', [
                'password'              => '',
                'password_confirmation' => '',
            ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_guest_cannot_store_password(): void
    {
        $response = $this->post('/set-password', [
            'password'              => 'NewSecureP@ss1',
            'password_confirmation' => 'NewSecureP@ss1',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_store_rejects_weak_password(): void
    {
        $user = User::factory()->create(['needs_password_setup' => true]);

        $response = $this->actingAs($user)
            ->post('/set-password', [
                'password'              => 'short',
                'password_confirmation' => 'short',
            ]);

        $response->assertSessionHasErrors('password');

        $user->refresh();
        $this->assertTrue($user->needs_password_setup);
    }

    public function test_store_does_not_clear_flag_on_validation_failure(): void
    {
        $user = User::factory()->create(['needs_password_setup' => true]);

        $this->actingAs($user)
            ->post('/set-password', [
                'password'              => '',
                'password_confirmation' => '',
            ]);

        $user->refresh();
        $this->assertTrue($user->needs_password_setup);
    }

    public function test_show_accessible_even_when_needs_password_setup_is_false(): void
    {
        // The route is auth-only, not restricted to needs_password_setup users
        $user = User::factory()->create(['needs_password_setup' => false]);

        $response = $this->actingAs($user)->get('/set-password');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Auth/SetPassword'));
    }
}
