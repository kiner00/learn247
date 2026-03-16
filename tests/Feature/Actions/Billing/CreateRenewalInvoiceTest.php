<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\CreateRenewalInvoice;
use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateRenewalInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_xendit_invoice_and_updates_subscription(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'price'    => 500,
            'currency' => 'PHP',
        ]);
        $subscriber = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $subscriber->id,
            'status'       => Subscription::STATUS_ACTIVE,
        ]);

        $xenditMock = Mockery::mock(XenditService::class);
        $xenditMock->shouldReceive('createInvoice')
            ->once()
            ->withArgs(function (array $data) use ($community, $subscriber) {
                return str_contains($data['external_id'], "renew_{$community->id}_{$subscriber->id}")
                    && $data['amount'] === (float) $community->price
                    && $data['currency'] === 'PHP'
                    && str_contains($data['description'], $community->name)
                    && $data['customer']['email'] === $subscriber->email;
            })
            ->andReturn([
                'id'          => 'inv_renewal_123',
                'invoice_url' => 'https://checkout.xendit.co/renew/123',
            ]);

        $action = new CreateRenewalInvoice($xenditMock);
        $url = $action->execute($subscription);

        $this->assertSame('https://checkout.xendit.co/renew/123', $url);

        $subscription->refresh();
        $this->assertSame('inv_renewal_123', $subscription->xendit_id);
        $this->assertSame('https://checkout.xendit.co/renew/123', $subscription->xendit_invoice_url);
    }

    public function test_propagates_exception_from_xendit(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'price'    => 500,
            'currency' => 'PHP',
        ]);
        $subscriber = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $subscriber->id,
            'status'       => Subscription::STATUS_ACTIVE,
        ]);

        $xenditMock = Mockery::mock(XenditService::class);
        $xenditMock->shouldReceive('createInvoice')
            ->once()
            ->andThrow(new \RuntimeException('Failed to create Xendit invoice'));

        $action = new CreateRenewalInvoice($xenditMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create Xendit invoice');

        $action->execute($subscription);
    }
}
