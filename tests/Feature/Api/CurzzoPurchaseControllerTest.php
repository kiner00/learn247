<?php

namespace Tests\Feature\Api;

use App\Models\Community;
use App\Models\Curzzo;
use App\Models\CurzzoPurchase;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CurzzoPurchaseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeCurzzo(): Curzzo
    {
        return Curzzo::factory()->paidMonthly()->create();
    }

    private function makePurchase(User $user, array $overrides = []): CurzzoPurchase
    {
        return CurzzoPurchase::create(array_merge([
            'user_id' => $user->id,
            'curzzo_id' => $this->makeCurzzo()->id,
            'status' => CurzzoPurchase::STATUS_PENDING,
            'xendit_id' => 'inv_'.uniqid(),
        ], $overrides));
    }

    // ─── check-status ────────────────────────────────────────────────────────

    public function test_check_status_returns_purchase_state(): void
    {
        $user = User::factory()->create();
        $purchase = $this->makePurchase($user, [
            'status' => CurzzoPurchase::STATUS_PAID,
            'paid_at' => now(),
            'expires_at' => now()->addMonth(),
            'xendit_plan_id' => 'repl_cz_status',
            'recurring_status' => 'ACTIVE',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/curzzo-purchases/{$purchase->id}/check-status")
            ->assertOk()
            ->assertJsonPath('data.id', $purchase->id)
            ->assertJsonPath('data.status', CurzzoPurchase::STATUS_PAID)
            ->assertJsonPath('data.is_paid', true)
            ->assertJsonPath('data.is_recurring', true)
            ->assertJsonPath('data.recurring_status', 'ACTIVE');
    }

    public function test_check_status_returns_pending_for_new_purchase(): void
    {
        $user = User::factory()->create();
        $purchase = $this->makePurchase($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/curzzo-purchases/{$purchase->id}/check-status")
            ->assertOk()
            ->assertJsonPath('data.status', CurzzoPurchase::STATUS_PENDING)
            ->assertJsonPath('data.is_paid', false)
            ->assertJsonPath('data.is_recurring', false);
    }

    public function test_check_status_requires_auth(): void
    {
        $user = User::factory()->create();
        $purchase = $this->makePurchase($user);

        $this->postJson("/api/curzzo-purchases/{$purchase->id}/check-status")
            ->assertUnauthorized();
    }

    public function test_check_status_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $purchase = $this->makePurchase($owner);

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/curzzo-purchases/{$purchase->id}/check-status")
            ->assertForbidden();
    }

    public function test_check_status_404s_for_unknown_purchase(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/curzzo-purchases/999999/check-status')
            ->assertNotFound();
    }

    // ─── cancel-recurring ────────────────────────────────────────────────────

    public function test_cancel_recurring_deactivates_plan(): void
    {
        $user = User::factory()->create();
        $purchase = $this->makePurchase($user, [
            'status' => CurzzoPurchase::STATUS_PAID,
            'xendit_plan_id' => 'repl_cz_cancel_api',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addMonth(),
        ]);

        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('deactivateRecurringPlan')
            ->once()
            ->with('repl_cz_cancel_api')
            ->andReturn(['status' => 'INACTIVE']);
        $this->app->instance(XenditService::class, $xendit);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/curzzo-purchases/{$purchase->id}/cancel-recurring")
            ->assertOk()
            ->assertJsonPath('data.id', $purchase->id)
            ->assertJsonPath('data.recurring_status', 'INACTIVE');

        $this->assertEquals('INACTIVE', $purchase->fresh()->recurring_status);
    }

    public function test_cancel_recurring_requires_auth(): void
    {
        $user = User::factory()->create();
        $purchase = $this->makePurchase($user, ['xendit_plan_id' => 'repl_x']);

        $this->postJson("/api/curzzo-purchases/{$purchase->id}/cancel-recurring")
            ->assertUnauthorized();
    }

    public function test_cancel_recurring_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $purchase = $this->makePurchase($owner, [
            'xendit_plan_id' => 'repl_cz_owned',
            'recurring_status' => 'ACTIVE',
        ]);

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/curzzo-purchases/{$purchase->id}/cancel-recurring")
            ->assertForbidden();
    }

    public function test_cancel_recurring_rejects_non_recurring_purchase(): void
    {
        $user = User::factory()->create();
        $purchase = $this->makePurchase($user); // no xendit_plan_id

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/curzzo-purchases/{$purchase->id}/cancel-recurring")
            ->assertStatus(400);
    }
}
