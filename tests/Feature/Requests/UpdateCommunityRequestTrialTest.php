<?php

namespace Tests\Feature\Requests;

use App\Models\Community;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateCommunityRequestTrialTest extends TestCase
{
    use RefreshDatabase;

    private function patchPricing(User $owner, Community $community, array $data)
    {
        return $this->actingAs($owner)
            ->from("/communities/{$community->slug}/settings")
            ->patch("/communities/{$community->slug}", array_merge([
                'name' => $community->name,
            ], $data));
    }

    private function makePaidCommunity(User $owner, array $overrides = []): Community
    {
        return Community::factory()->paid(999)->create(array_merge([
            'owner_id' => $owner->id,
        ], $overrides));
    }

    public function test_per_user_trial_requires_trial_days(): void
    {
        $owner = User::factory()->create();
        $community = $this->makePaidCommunity($owner);

        $response = $this->patchPricing($owner, $community, [
            'price' => 999,
            'currency' => 'PHP',
            'trial_mode' => 'per_user',
        ]);

        $response->assertSessionHasErrors('trial_days');
    }

    public function test_window_trial_requires_future_free_until(): void
    {
        $owner = User::factory()->create();
        $community = $this->makePaidCommunity($owner);

        $response = $this->patchPricing($owner, $community, [
            'price' => 999,
            'currency' => 'PHP',
            'trial_mode' => 'window',
            'free_until' => now()->subDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors('free_until');
    }

    public function test_first_month_price_cannot_exceed_price(): void
    {
        $owner = User::factory()->create();
        $community = $this->makePaidCommunity($owner);

        $response = $this->patchPricing($owner, $community, [
            'price' => 500,
            'currency' => 'PHP',
            'first_month_price' => 999,
        ]);

        $response->assertSessionHasErrors('first_month_price');
    }

    public function test_trial_mode_blocked_when_community_is_free(): void
    {
        $owner = User::factory()->create();
        $community = $this->makePaidCommunity($owner);

        $response = $this->patchPricing($owner, $community, [
            'price' => 0,
            'currency' => 'PHP',
            'trial_mode' => 'per_user',
            'trial_days' => 7,
        ]);

        $response->assertSessionHasErrors('trial_mode');
    }

    public function test_valid_per_user_trial_persists(): void
    {
        $owner = User::factory()->create();
        $community = $this->makePaidCommunity($owner);

        $response = $this->patchPricing($owner, $community, [
            'price' => 999,
            'currency' => 'PHP',
            'trial_mode' => 'per_user',
            'trial_days' => 14,
            'first_month_price' => 199,
        ]);

        $response->assertSessionHasNoErrors();
        $community->refresh();
        $this->assertSame(Community::TRIAL_PER_USER, $community->trial_mode);
        $this->assertSame(14, $community->trial_days);
        $this->assertSame('199.00', (string) $community->first_month_price);
    }

    public function test_switching_to_none_clears_trial_fields(): void
    {
        $owner = User::factory()->create();
        $community = $this->makePaidCommunity($owner, [
            'trial_mode' => 'per_user',
            'trial_days' => 7,
        ]);

        $response = $this->patchPricing($owner, $community, [
            'price' => 999,
            'currency' => 'PHP',
            'trial_mode' => 'none',
            'trial_days' => 7,
        ]);

        $response->assertSessionHasNoErrors();
        $community->refresh();
        $this->assertSame(Community::TRIAL_NONE, $community->trial_mode);
        $this->assertNull($community->trial_days);
    }

    public function test_free_until_stored_as_end_of_day_for_window_trial(): void
    {
        $owner = User::factory()->create();
        $community = $this->makePaidCommunity($owner);
        $targetDate = now()->addDays(14)->toDateString();

        $response = $this->patchPricing($owner, $community, [
            'price' => 999,
            'currency' => 'PHP',
            'trial_mode' => 'window',
            'free_until' => $targetDate,
        ]);

        $response->assertSessionHasNoErrors();
        $community->refresh();
        $this->assertSame(Community::TRIAL_WINDOW, $community->trial_mode);
        $this->assertNotNull($community->free_until);
        $this->assertSame(23, $community->free_until->hour);
        $this->assertSame(59, $community->free_until->minute);
    }
}
