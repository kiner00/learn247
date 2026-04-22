<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\StartCurzzoCheckout;
use App\Models\Community;
use App\Models\Curzzo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StartCurzzoCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private StartCurzzoCheckout $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(StartCurzzoCheckout::class);
    }

    public function test_rejects_inclusive_access_type_even_with_stale_price(): void
    {
        // Regression guard: a bot that was once paid_monthly but was later
        // switched to 'inclusive' may still carry a non-zero `price`. It must
        // not be purchasable through Curzzo checkout.
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $curzzo = Curzzo::factory()->create([
            'community_id' => $community->id,
            'access_type' => 'inclusive',
            'price' => 99.00,
            'currency' => 'PHP',
            'billing_type' => 'monthly',
        ]);

        $this->expectException(ValidationException::class);
        $this->action->execute($user, $curzzo);
    }

    public function test_rejects_free_access_type(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $curzzo = Curzzo::factory()->create([
            'community_id' => $community->id,
            'access_type' => 'free',
        ]);

        $this->expectException(ValidationException::class);
        $this->action->execute($user, $curzzo);
    }

    public function test_rejects_member_once_access_type(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $curzzo = Curzzo::factory()->create([
            'community_id' => $community->id,
            'access_type' => 'member_once',
            'price' => 49.00,
        ]);

        $this->expectException(ValidationException::class);
        $this->action->execute($user, $curzzo);
    }
}
