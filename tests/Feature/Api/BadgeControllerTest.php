<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BadgeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_badges_returns_member_and_creator_badges(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/badges');

        $response->assertOk()
            ->assertJsonStructure([
                'member_badges',
                'creator_badges',
            ]);
    }

    public function test_get_badges_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/badges')
            ->assertUnauthorized();
    }
}
