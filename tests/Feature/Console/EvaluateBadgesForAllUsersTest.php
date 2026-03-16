<?php

namespace Tests\Feature\Console;

use App\Models\User;
use App\Services\BadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class EvaluateBadgesForAllUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_evaluates_badges_for_all_users(): void
    {
        User::factory()->count(3)->create();

        $badgeService = Mockery::mock(BadgeService::class);
        $badgeService->shouldReceive('evaluate')->times(3);
        $this->instance(BadgeService::class, $badgeService);

        $this->artisan('badges:evaluate-all')
            ->assertExitCode(0);
    }

    public function test_command_handles_zero_users(): void
    {
        $badgeService = Mockery::mock(BadgeService::class);
        $badgeService->shouldNotReceive('evaluate');
        $this->instance(BadgeService::class, $badgeService);

        $this->artisan('badges:evaluate-all')
            ->assertExitCode(0);
    }

    public function test_command_outputs_completion_message(): void
    {
        User::factory()->count(2)->create();

        $badgeService = Mockery::mock(BadgeService::class);
        $badgeService->shouldReceive('evaluate')->times(2);
        $this->instance(BadgeService::class, $badgeService);

        $this->artisan('badges:evaluate-all')
            ->expectsOutputToContain('Done — evaluated badges for 2 users.')
            ->assertExitCode(0);
    }
}
