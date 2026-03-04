<?php

namespace Tests\Unit\Services;

use App\Services\XenditService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XenditServiceTest extends TestCase
{
    // ─── verifyCallbackToken ──────────────────────────────────────────────────

    public function test_verify_callback_token_returns_true_for_matching_token(): void
    {
        config(['services.xendit.callback_token' => 'my_secret_token']);
        $service = new XenditService();

        $this->assertTrue($service->verifyCallbackToken('my_secret_token'));
    }

    public function test_verify_callback_token_returns_false_for_wrong_token(): void
    {
        config(['services.xendit.callback_token' => 'my_secret_token']);
        $service = new XenditService();

        $this->assertFalse($service->verifyCallbackToken('wrong_token'));
    }

    public function test_verify_callback_token_returns_false_for_null_token(): void
    {
        config(['services.xendit.callback_token' => 'my_secret_token']);
        $service = new XenditService();

        $this->assertFalse($service->verifyCallbackToken(null));
    }

    public function test_verify_callback_token_returns_false_when_config_token_is_empty(): void
    {
        config(['services.xendit.callback_token' => '']);
        $service = new XenditService();

        $this->assertFalse($service->verifyCallbackToken('some_token'));
    }

    // ─── createInvoice ────────────────────────────────────────────────────────

    public function test_create_invoice_returns_response_array_on_success(): void
    {
        Http::fake([
            'https://api.xendit.co/v2/invoices' => Http::response([
                'id'          => 'inv_test_123',
                'invoice_url' => 'https://checkout.xendit.co/v2/inv_test_123',
                'status'      => 'PENDING',
            ], 200),
        ]);

        config(['services.xendit.secret_key' => 'xnd_test_key']);
        $service = new XenditService();

        $result = $service->createInvoice([
            'external_id' => 'sub_1',
            'amount'      => 499,
            'currency'    => 'PHP',
        ]);

        $this->assertSame('inv_test_123', $result['id']);
        $this->assertSame('PENDING', $result['status']);
    }

    public function test_create_invoice_throws_runtime_exception_on_failure(): void
    {
        Http::fake([
            'https://api.xendit.co/v2/invoices' => Http::response(['error_code' => 'INVALID_API_KEY'], 401),
        ]);

        config(['services.xendit.secret_key' => 'bad_key']);
        $service = new XenditService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to create Xendit invoice/');

        $service->createInvoice(['external_id' => 'sub_1', 'amount' => 499]);
    }

    // ─── getInvoice ───────────────────────────────────────────────────────────

    public function test_get_invoice_returns_response_array_on_success(): void
    {
        Http::fake([
            'https://api.xendit.co/v2/invoices/inv_abc' => Http::response([
                'id'     => 'inv_abc',
                'status' => 'PAID',
            ], 200),
        ]);

        config(['services.xendit.secret_key' => 'xnd_test_key']);
        $service = new XenditService();

        $result = $service->getInvoice('inv_abc');

        $this->assertSame('inv_abc', $result['id']);
        $this->assertSame('PAID', $result['status']);
    }

    public function test_get_invoice_throws_runtime_exception_on_failure(): void
    {
        Http::fake([
            'https://api.xendit.co/v2/invoices/bad_id' => Http::response([], 404),
        ]);

        config(['services.xendit.secret_key' => 'xnd_test_key']);
        $service = new XenditService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to fetch Xendit invoice/');

        $service->getInvoice('bad_id');
    }
}
