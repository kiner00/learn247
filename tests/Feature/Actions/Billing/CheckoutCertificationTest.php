<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\CheckoutCertification;
use App\Models\CertificationPurchase;
use App\Models\Community;
use App\Models\CourseCertification;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckoutCertificationTest extends TestCase
{
    use RefreshDatabase;

    private function setupPaidCert(): array
    {
        $community = Community::factory()->create(['currency' => 'PHP']);
        $cert = CourseCertification::factory()->paid(500)->create([
            'community_id' => $community->id,
        ]);
        $user = User::factory()->create();

        return [$user, $community, $cert];
    }

    public function test_execute_creates_purchase_and_returns_checkout_url(): void
    {
        [$user, $community, $cert] = $this->setupPaidCert();

        $this->mock(XenditService::class, function ($mock) {
            $mock->shouldReceive('createInvoice')->once()->andReturn([
                'id' => 'inv_123',
                'invoice_url' => 'https://checkout.xendit.co/inv_123',
            ]);
        });

        $action = app(CheckoutCertification::class);
        $result = $action->execute($user, $community, $cert, 'https://example.com/success');

        $this->assertArrayHasKey('purchase', $result);
        $this->assertArrayHasKey('checkout_url', $result);
        $this->assertEquals('https://checkout.xendit.co/inv_123', $result['checkout_url']);

        $this->assertDatabaseHas('certification_purchases', [
            'user_id' => $user->id,
            'certification_id' => $cert->id,
            'xendit_id' => 'inv_123',
            'status' => CertificationPurchase::STATUS_PENDING,
        ]);
    }

    public function test_execute_throws_for_free_certification(): void
    {
        $community = Community::factory()->create();
        $cert = CourseCertification::factory()->create([
            'community_id' => $community->id,
            'price' => 0,
        ]);
        $user = User::factory()->create();

        $action = app(CheckoutCertification::class);

        $this->expectException(ValidationException::class);
        $action->execute($user, $community, $cert, 'https://example.com/success');
    }

    public function test_execute_throws_for_already_purchased(): void
    {
        [$user, $community, $cert] = $this->setupPaidCert();

        CertificationPurchase::factory()->paid()->create([
            'user_id' => $user->id,
            'certification_id' => $cert->id,
        ]);

        $action = app(CheckoutCertification::class);

        $this->expectException(ValidationException::class);
        $action->execute($user, $community, $cert, 'https://example.com/success');
    }

    public function test_execute_resolves_affiliate_from_subscription(): void
    {
        [$user, $community, $cert] = $this->setupPaidCert();

        // Create a subscription with an affiliate
        $affiliate = \App\Models\Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'code' => 'AFF123',
        ]);

        Subscription::factory()->active()->create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'affiliate_id' => $affiliate->id,
        ]);

        $this->mock(XenditService::class, function ($mock) {
            $mock->shouldReceive('createInvoice')->once()->andReturn([
                'id' => 'inv_456',
                'invoice_url' => 'https://checkout.xendit.co/inv_456',
            ]);
        });

        $action = app(CheckoutCertification::class);
        $result = $action->execute($user, $community, $cert, 'https://example.com/success');

        $this->assertEquals($affiliate->id, $result['purchase']->affiliate_id);
    }

    public function test_execute_updates_existing_pending_purchase(): void
    {
        [$user, $community, $cert] = $this->setupPaidCert();

        // Create a pending purchase
        CertificationPurchase::factory()->create([
            'user_id' => $user->id,
            'certification_id' => $cert->id,
            'xendit_id' => 'old_inv',
            'status' => CertificationPurchase::STATUS_PENDING,
        ]);

        $this->mock(XenditService::class, function ($mock) {
            $mock->shouldReceive('createInvoice')->once()->andReturn([
                'id' => 'inv_new',
                'invoice_url' => 'https://checkout.xendit.co/inv_new',
            ]);
        });

        $action = app(CheckoutCertification::class);
        $result = $action->execute($user, $community, $cert, 'https://example.com/success');

        // Should update the existing record, not create a new one
        $this->assertEquals(1, CertificationPurchase::where('user_id', $user->id)
            ->where('certification_id', $cert->id)->count());
        $this->assertEquals('inv_new', $result['purchase']->xendit_id);
    }

    public function test_execute_rethrows_xendit_exception(): void
    {
        [$user, $community, $cert] = $this->setupPaidCert();

        $this->mock(XenditService::class, function ($mock) {
            $mock->shouldReceive('createInvoice')->once()->andThrow(new \RuntimeException('Xendit error'));
        });

        $action = app(CheckoutCertification::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Xendit error');
        $action->execute($user, $community, $cert, 'https://example.com/success');
    }
}
