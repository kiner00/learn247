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
        $service = new XenditService;

        $result = $service->createInvoice([
            'external_id' => 'order-001',
            'amount' => 500,
            'currency' => 'PHP',
        ]);

        $this->assertEquals('inv_123', $result['id']);
    }

    public function test_create_invoice_throws_on_failure(): void
    {
        Http::fake([
            'https://api.xendit.co/v2/invoices' => Http::response(['error' => 'bad request'], 400),
        ]);

        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'cb_token']);
        $service = new XenditService;

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
        $service = new XenditService;

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
        $service = new XenditService;

        $result = $service->createPayout([
            'reference_id' => 'payout-001',
            'amount' => 1000,
        ]);

        $this->assertEquals('po_123', $result['id']);
    }

    public function test_get_balance_returns_float(): void
    {
        Http::fake([
            'https://api.xendit.co/balance*' => Http::response(['balance' => 50000], 200),
        ]);

        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'cb_token']);
        $service = new XenditService;

        $result = $service->getBalance();

        $this->assertEquals(50000.0, $result);
    }

    public function test_get_balance_returns_zero_on_failure(): void
    {
        Http::fake([
            'https://api.xendit.co/balance*' => Http::response([], 500),
        ]);

        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'cb_token']);
        $service = new XenditService;

        $result = $service->getBalance();

        $this->assertEquals(0.0, $result);
    }

    public function test_verify_callback_token_valid(): void
    {
        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'my-secret-token']);
        $service = new XenditService;

        $this->assertTrue($service->verifyCallbackToken('my-secret-token'));
    }

    public function test_verify_callback_token_invalid(): void
    {
        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'my-secret-token']);
        $service = new XenditService;

        $this->assertFalse($service->verifyCallbackToken('wrong-token'));
    }

    public function test_verify_callback_token_empty(): void
    {
        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'my-secret-token']);
        $service = new XenditService;

        $this->assertFalse($service->verifyCallbackToken(null));
        $this->assertFalse($service->verifyCallbackToken(''));
    }

    public function test_get_invoice_throws_on_failure(): void
    {
        Http::fake([
            'https://api.xendit.co/v2/invoices/inv_bad' => Http::response(['error' => 'NOT_FOUND'], 404),
        ]);

        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'cb_token']);
        $service = new XenditService;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch Xendit invoice');
        $service->getInvoice('inv_bad');
    }

    public function test_create_payout_throws_on_failure(): void
    {
        Http::fake([
            'https://api.xendit.co/v2/payouts' => Http::response(
                ['message' => 'INSUFFICIENT_BALANCE'],
                400
            ),
        ]);

        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'cb_token']);
        $service = new XenditService;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Xendit payout failed');
        $service->createPayout([
            'reference_id' => 'payout-fail',
            'amount' => 999999,
        ]);
    }

    public function test_create_payout_uses_generated_idempotency_key_without_reference_id(): void
    {
        Http::fake([
            'https://api.xendit.co/v2/payouts' => Http::response(
                ['id' => 'po_auto', 'status' => 'ACCEPTED'],
                200
            ),
        ]);

        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'cb_token']);
        $service = new XenditService;

        $result = $service->createPayout(['amount' => 500]);

        $this->assertEquals('po_auto', $result['id']);

        Http::assertSent(function ($request) {
            return str_starts_with($request->header('Idempotency-key')[0], 'payout-');
        });
    }

    public function test_verify_callback_token_returns_false_when_config_empty(): void
    {
        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => '']);
        $service = new XenditService;

        $this->assertFalse($service->verifyCallbackToken('some-token'));
    }

    public function test_get_balance_with_custom_account_type(): void
    {
        Http::fake([
            'https://api.xendit.co/balance*' => Http::response(['balance' => 25000], 200),
        ]);

        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'cb_token']);
        $service = new XenditService;

        $result = $service->getBalance('HOLDING');

        $this->assertEquals(25000.0, $result);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'account_type=HOLDING');
        });
    }

    // ─── getPayout ───────────────────────────────────────────────────────────

    public function test_get_payout_returns_response(): void
    {
        Http::fake([
            'https://api.xendit.co/v2/payouts/po_123' => Http::response(
                ['id' => 'po_123', 'status' => 'SUCCEEDED'],
                200
            ),
        ]);

        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'cb_token']);
        $service = new XenditService;

        $result = $service->getPayout('po_123');

        $this->assertEquals('po_123', $result['id']);
        $this->assertEquals('SUCCEEDED', $result['status']);
    }

    public function test_get_payout_throws_on_failure(): void
    {
        Http::fake([
            'https://api.xendit.co/v2/payouts/po_bad' => Http::response(['error' => 'NOT_FOUND'], 404),
        ]);

        config(['services.xendit.secret_key' => 'test_key', 'services.xendit.callback_token' => 'cb_token']);
        $service = new XenditService;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch Xendit payout');
        $service->getPayout('po_bad');
    }

    // ─── collectionFee edge cases ────────────────────────────────────────────

    public function test_collection_fee_maya(): void
    {
        $this->assertEquals(round(1000 * 0.018, 2), XenditService::collectionFee('MAYA', 1000));
        $this->assertEquals(round(1000 * 0.018, 2), XenditService::collectionFee('PAYMAYA', 1000));
    }

    public function test_collection_fee_grabpay_shopeepay(): void
    {
        $this->assertEquals(round(1000 * 0.020, 2), XenditService::collectionFee('GRABPAY', 1000));
        $this->assertEquals(round(1000 * 0.020, 2), XenditService::collectionFee('SHOPEEPAY', 1000));
    }

    public function test_collection_fee_credit_card(): void
    {
        $this->assertEquals(round(1000 * 0.032 + 10, 2), XenditService::collectionFee('CREDIT_CARD', 1000));
        $this->assertEquals(round(1000 * 0.032 + 10, 2), XenditService::collectionFee('VISA', 1000));
    }

    public function test_collection_fee_qrph(): void
    {
        // QRPH 1.4% min 15
        $this->assertEquals(15.0, XenditService::collectionFee('QRPH', 500)); // 500*0.014=7, min 15
        $this->assertEquals(round(2000 * 0.014, 2), XenditService::collectionFee('QRPH', 2000)); // 28 > 15
    }

    public function test_collection_fee_direct_debit(): void
    {
        // DD_BPI 1% min 25
        $this->assertEquals(25.0, XenditService::collectionFee('DD_BPI', 1000)); // 10 < 25
        $this->assertEquals(round(5000 * 0.010, 2), XenditService::collectionFee('BPI', 5000)); // 50 > 25
    }

    public function test_collection_fee_unknown_channel(): void
    {
        $this->assertEquals(0.0, XenditService::collectionFee('UNKNOWN_CHANNEL', 1000));
    }
}
