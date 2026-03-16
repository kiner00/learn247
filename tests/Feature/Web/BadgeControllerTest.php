<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Queries\Badge\GetBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class BadgeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_badges_page(): void
    {
        $this->mock(GetBadges::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                ->once()
                ->andReturn([
                    'member'  => [['name' => 'First Post', 'earned' => true]],
                    'creator' => [['name' => 'Community Creator', 'earned' => false]],
                ]);
        });

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('badges'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Badges/Index')
                ->has('memberBadges')
                ->has('creatorBadges')
            );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('badges'))->assertRedirect('/login');
    }
}
