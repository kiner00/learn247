<?php

namespace Tests\Feature\Web;

use App\Models\Affiliate;
use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GuestCheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(): array
    {
        return [
            'first_name' => 'Juan',
            'last_name'  => 'Dela Cruz',
            'email'      => 'juan@example.com',
            'phone'      => '09171234567',
        ];
    }

    private function createActiveAffiliate(?Community $community = null): Affiliate
    {
        $community ??= Community::factory()->paid()->create();

        return Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $community->owner_id,
            'code'         => 'REF-TEST',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);
    }

    // ─── process ────────────────────────────────────────────────────────────────

    public function test_valid_code_creates_user_and_redirects_to_checkout(): void
    {
        Http::fake([
            '*' => Http::response([
                'id'          => 'inv_test_123',
                'invoice_url' => 'https://checkout.xendit.co/inv_test_123',
            ]),
        ]);

        $affiliate = $this->createActiveAffiliate();

        $response = $this->post("/ref-checkout/{$affiliate->code}", $this->validPayload());

        $response->assertRedirect('https://checkout.xendit.co/inv_test_123');

        $this->assertDatabaseHas('users', [
            'email' => 'juan@example.com',
            'name'  => 'Juan Dela Cruz',
        ]);
    }

    public function test_invalid_code_redirects_to_communities(): void
    {
        $response = $this->post('/ref-checkout/INVALID-CODE', $this->validPayload());

        $response->assertRedirect(route('communities.index'));
    }

    public function test_inactive_affiliate_redirects_to_communities(): void
    {
        $community = Community::factory()->paid()->create();

        Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $community->owner_id,
            'code'         => 'INACTIVE-CODE',
            'status'       => Affiliate::STATUS_INACTIVE,
        ]);

        $response = $this->post('/ref-checkout/INACTIVE-CODE', $this->validPayload());

        $response->assertRedirect(route('communities.index'));
    }

    public function test_validation_fails_without_required_fields(): void
    {
        $affiliate = $this->createActiveAffiliate();

        $response = $this->post("/ref-checkout/{$affiliate->code}", []);

        $response->assertSessionHasErrors(['first_name', 'last_name', 'email', 'phone']);
    }

    public function test_validation_fails_with_invalid_email(): void
    {
        $affiliate = $this->createActiveAffiliate();

        $response = $this->post("/ref-checkout/{$affiliate->code}", [
            'first_name' => 'Juan',
            'last_name'  => 'Dela Cruz',
            'email'      => 'not-an-email',
            'phone'      => '09171234567',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_existing_user_with_active_subscription_gets_error(): void
    {
        $community = Community::factory()->paid()->create();
        $affiliate = $this->createActiveAffiliate($community);

        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $existingUser->id,
        ]);

        $response = $this->post("/ref-checkout/{$affiliate->code}", [
            'first_name' => 'Existing',
            'last_name'  => 'User',
            'email'      => 'existing@example.com',
            'phone'      => '09171234567',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
    }

    public function test_existing_user_without_subscription_proceeds_to_checkout(): void
    {
        Http::fake([
            '*' => Http::response([
                'id'          => 'inv_exist_123',
                'invoice_url' => 'https://checkout.xendit.co/inv_exist_123',
            ]),
        ]);

        $community = Community::factory()->paid()->create();
        $affiliate = $this->createActiveAffiliate($community);

        User::factory()->create(['email' => 'returning@example.com']);

        $response = $this->post("/ref-checkout/{$affiliate->code}", [
            'first_name' => 'Returning',
            'last_name'  => 'User',
            'email'      => 'returning@example.com',
            'phone'      => '09171234567',
        ]);

        $response->assertRedirect('https://checkout.xendit.co/inv_exist_123');

        $this->assertDatabaseHas('subscriptions', [
            'community_id' => $community->id,
            'status'       => Subscription::STATUS_PENDING,
        ]);
    }

    public function test_new_user_gets_created_with_generated_username(): void
    {
        Http::fake([
            '*' => Http::response([
                'id'          => 'inv_new_123',
                'invoice_url' => 'https://checkout.xendit.co/inv_new_123',
            ]),
        ]);

        $affiliate = $this->createActiveAffiliate();

        $this->post("/ref-checkout/{$affiliate->code}", [
            'first_name' => 'Maria',
            'last_name'  => 'Santos',
            'email'      => 'maria@example.com',
            'phone'      => '09181234567',
        ]);

        $user = User::where('email', 'maria@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Maria Santos', $user->name);
        $this->assertTrue($user->needs_password_setup);
        $this->assertNotNull($user->username);
    }
}
