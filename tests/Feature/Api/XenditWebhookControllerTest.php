<?php

namespace Tests\Feature\Api;

use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class XenditWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_webhook_returns_200(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_test',
            'status' => Subscription::STATUS_PENDING,
        ]);

        $this->instance(XenditService::class, tap(Mockery::mock(XenditService::class), function ($mock) {
            $mock->shouldReceive('verifyCallbackToken')->andReturn(true);
        }));

        $response = $this->postJson('/api/xendit/webhook', [
            'id' => 'inv_test',
            'status' => 'PAID',
            'amount' => 499,
            'currency' => 'PHP',
        ], ['x-callback-token' => 'valid']);

        $response->assertOk()->assertJsonPath('message', 'Webhook processed.');
    }

    public function test_invalid_token_returns_401(): void
    {
        $this->instance(XenditService::class, tap(Mockery::mock(XenditService::class), function ($mock) {
            $mock->shouldReceive('verifyCallbackToken')->andReturn(false);
        }));

        $this->postJson('/api/xendit/webhook', ['id' => 'inv_bad', 'status' => 'PAID'], [
            'x-callback-token' => 'bad_token',
        ])->assertUnauthorized();
    }
}
