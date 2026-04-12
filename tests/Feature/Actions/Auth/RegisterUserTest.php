<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\RegisterUser;
use App\Contracts\BadgeEvaluator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class RegisterUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_is_created_with_correct_fields(): void
    {
        $badges = Mockery::mock(BadgeEvaluator::class);
        $badges->shouldReceive('evaluate')->once();

        $action = new RegisterUser($badges);

        $user = $action->execute([
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'email'      => 'john@example.com',
            'password'   => 'secret123',
        ]);

        $this->assertDatabaseHas('users', [
            'name'  => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_username_is_generated_from_name_and_id(): void
    {
        $badges = Mockery::mock(BadgeEvaluator::class);
        $badges->shouldReceive('evaluate')->once();

        $action = new RegisterUser($badges);

        $user = $action->execute([
            'first_name' => 'Jane',
            'last_name'  => 'Smith',
            'email'      => 'jane@example.com',
            'password'   => 'secret123',
        ]);

        $this->assertSame("jane-smith-{$user->id}", $user->username);
    }

    public function test_phone_is_optional(): void
    {
        $badges = Mockery::mock(BadgeEvaluator::class);
        $badges->shouldReceive('evaluate')->once();

        $action = new RegisterUser($badges);

        $user = $action->execute([
            'first_name' => 'No',
            'last_name'  => 'Phone',
            'email'      => 'nophone@example.com',
            'password'   => 'secret123',
        ]);

        $this->assertNull($user->phone);
    }

    public function test_badge_evaluator_is_called_after_registration(): void
    {
        $badges = Mockery::mock(BadgeEvaluator::class);
        $badges->shouldReceive('evaluate')
            ->once()
            ->with(Mockery::type(User::class));

        $action = new RegisterUser($badges);

        $action->execute([
            'first_name' => 'Badge',
            'last_name'  => 'Test',
            'email'      => 'badge@example.com',
            'password'   => 'secret123',
        ]);
    }
}
