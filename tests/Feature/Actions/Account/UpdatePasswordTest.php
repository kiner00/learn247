<?php

namespace Tests\Feature\Actions\Account;

use App\Actions\Account\UpdatePassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UpdatePasswordTest extends TestCase
{
    use RefreshDatabase;

    private UpdatePassword $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UpdatePassword();
    }

    public function test_success_updates_password(): void
    {
        $user = User::factory()->create();

        $this->action->execute($user, 'password', 'new-secret-123');

        $this->assertTrue(Hash::check('new-secret-123', $user->fresh()->password));
    }

    public function test_wrong_current_password_throws_validation_exception(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Current password is incorrect.');
        $this->action->execute($user, 'wrong-current', 'new-password');
    }
}
