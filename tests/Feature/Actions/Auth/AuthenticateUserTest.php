<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\AuthenticateUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthenticateUserTest extends TestCase
{
    use RefreshDatabase;

    private AuthenticateUser $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new AuthenticateUser();
    }

    public function test_success_returns_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $result = $this->action->execute($user->email, 'password');

        $this->assertSame($user->id, $result->id);
        $this->assertEquals($user->email, $result->email);
    }

    public function test_bad_credentials_throws_validation_exception(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('These credentials do not match our records.');
        $this->action->execute($user->email, 'wrong-password');
    }

    public function test_inactive_user_throws_validation_exception(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Your account has been disabled. Please contact support.');
        $this->action->execute($user->email, 'password');
    }
}
