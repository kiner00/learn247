<?php

namespace Tests\Feature\Services;

use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XenditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_invoice_returns_response(): void
    {
        Http::fake([
            'https://api.xendit.co/v2/invoices' => Http::response(
                ['id' => 'inv_123', 'invoice_url' => 'https://checkout.xendit.co/inv_123'],
                200
            ),
        ]);

        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'cb_token']);
        $service = new XenditService();

        $result = $service->createInvoice([
            'external_id' => 'order-001',
            'amount'       => 500,
            'currency'     => 'PHP',
        ]);

        $this->assertEquals('inv_123', $result['id']);
    }

    public function test_create_invoice_throws_on_failure(): void
    {
        Http::fake([
            'https://api.xendit.co/v2/invoices' => Http::response(['error' => 'bad request'], 400),
        ]);

        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'cb_token']);
        $service = new XenditService();

        $this->expectException(\RuntimeException::class);
        $service->createInvoice(['external_id' => 'fail']);
    }

    public function test_get_invoice_returns_response(): void
    {
        Http::fake([
            'https://api.xendit.co/v2/invoices/inv_123' => Http::response(
                ['id' => 'inv_123', 'status' => 'PAID'],
                200
            ),
        ]);

        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'cb_token']);
        $service = new XenditService();

        $result = $service->getInvoice('inv_123');

        $this->assertEquals('PAID', $result['status']);
    }

    public function test_create_payout_returns_response(): void
    {
        Http::fake([
            'https://api.xendit.co/v2/payouts' => Http::response(
                ['id' => 'po_123', 'status' => 'ACCEPTED'],
                200
            ),
        ]);

        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'cb_token']);
        $service = new XenditService();

        $result = $service->createPayout([
            'reference_id' => 'payout-001',
            'amount'       => 1000,
        ]);

        $this->assertEquals('po_123', $result['id']);
    }

    public function test_get_balance_returns_float(): void
    {
        Http::fake([
            'https://api.xendit.co/balance*' => Http::response(['balance' => 50000], 200),
        ]);

        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'cb_token']);
        $service = new XenditService();

        $result = $service->getBalance();

        $this->assertEquals(50000.0, $result);
    }

    public function test_get_balance_returns_zero_on_failure(): void
    {
        Http::fake([
            'https://api.xendit.co/balance*' => Http::response([], 500),
        ]);

        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'cb_token']);
        $service = new XenditService();

        $result = $service->getBalance();

        $this->assertEquals(0.0, $result);
    }

    public function test_verify_callback_token_valid(): void
    {
        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'my-secret-token']);
        $service = new XenditService();

        $this->assertTrue($service->verifyCallbackToken('my-secret-token'));
    }

    public function test_verify_callback_token_invalid(): void
    {
        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'my-secret-token']);
        $service = new XenditService();

        $this->assertFalse($service->verifyCallbackToken('wrong-token'));
    }

    public function test_verify_callback_token_empty(): void
    {
        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'my-secret-token']);
        $service = new XenditService();

        $this->assertFalse($service->verifyCallbackToken(null));
        $this->assertFalse($service->verifyCallbackToken(''));
    }
}
