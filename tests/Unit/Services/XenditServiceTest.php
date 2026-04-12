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

    // ─── createPayout ────────────────────────────────────────────────────────

    public function test_create_payout_returns_response_array_on_success(): void
    {
        Http::fake([
            'https://api.xendit.co/v2/payouts' => Http::response([
                'id'           => 'disb_test_456',
                'reference_id' => 'payout-ref-001',
                'status'       => 'ACCEPTED',
            ], 200),
        ]);

        config(['services.xendit.secret_key' => 'xnd_test_key']);
        $service = new XenditService();

        $result = $service->createPayout([
            'reference_id'        => 'payout-ref-001',
            'channel_code'        => 'PH_GCASH',
            'channel_properties'  => ['account_number' => '09171234567'],
            'amount'              => 500,
            'currency'            => 'PHP',
        ]);

        $this->assertSame('disb_test_456', $result['id']);
        $this->assertSame('ACCEPTED', $result['status']);
    }

    public function test_create_payout_throws_runtime_exception_on_failure(): void
    {
        Http::fake([
            'https://api.xendit.co/v2/payouts' => Http::response([
                'error_code' => 'INSUFFICIENT_BALANCE',
                'message'    => 'Insufficient balance',
            ], 400),
        ]);

        config(['services.xendit.secret_key' => 'xnd_test_key']);
        $service = new XenditService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Xendit payout failed/');

        $service->createPayout(['reference_id' => 'ref-fail', 'amount' => 99999]);
    }

    // ─── getPayout ───────────────────────────────────────────────────────────

    public function test_get_payout_returns_response_array_on_success(): void
    {
        Http::fake([
            'https://api.xendit.co/v2/payouts/disb_abc' => Http::response([
                'id'     => 'disb_abc',
                'status' => 'SUCCEEDED',
            ], 200),
        ]);

        config(['services.xendit.secret_key' => 'xnd_test_key']);
        $service = new XenditService();

        $result = $service->getPayout('disb_abc');

        $this->assertSame('disb_abc', $result['id']);
        $this->assertSame('SUCCEEDED', $result['status']);
    }

    public function test_get_payout_throws_runtime_exception_on_failure(): void
    {
        Http::fake([
            'https://api.xendit.co/v2/payouts/bad_id' => Http::response([], 404),
        ]);

        config(['services.xendit.secret_key' => 'xnd_test_key']);
        $service = new XenditService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to fetch Xendit payout/');

        $service->getPayout('bad_id');
    }

    // ─── getBalance ──────────────────────────────────────────────────────────

    public function test_get_balance_returns_balance_on_success(): void
    {
        Http::fake([
            'https://api.xendit.co/balance*' => Http::response(['balance' => 125000], 200),
        ]);

        config(['services.xendit.secret_key' => 'xnd_test_key']);
        $service = new XenditService();

        $this->assertSame(125000.0, $service->getBalance());
    }

    public function test_get_balance_returns_zero_on_failure(): void
    {
        Http::fake([
            'https://api.xendit.co/balance*' => Http::response([], 500),
        ]);

        config(['services.xendit.secret_key' => 'xnd_test_key']);
        $service = new XenditService();

        $this->assertSame(0.0, $service->getBalance());
    }

    // ─── createCustomer ──────────────────────────────────────────────────────

    public function test_create_customer_returns_response_on_success(): void
    {
        Http::fake([
            'https://api.xendit.co/customers' => Http::response([
                'id'           => 'cust_001',
                'reference_id' => 'user-42',
            ], 200),
        ]);

        config(['services.xendit.secret_key' => 'xnd_test_key']);
        $service = new XenditService();

        $result = $service->createCustomer(['reference_id' => 'user-42', 'email' => 'test@example.com']);

        $this->assertSame('cust_001', $result['id']);
    }

    public function test_create_customer_throws_runtime_exception_on_failure(): void
    {
        Http::fake([
            'https://api.xendit.co/customers' => Http::response(['error_code' => 'INVALID'], 400),
        ]);

        config(['services.xendit.secret_key' => 'xnd_test_key']);
        $service = new XenditService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to create Xendit customer/');

        $service->createCustomer(['reference_id' => 'bad']);
    }

    // ─── createRecurringPlan ─────────────────────────────────────────────────

    public function test_create_recurring_plan_returns_response_on_success(): void
    {
        Http::fake([
            'https://api.xendit.co/recurring/plans' => Http::response([
                'id'     => 'repl_test_001',
                'status' => 'REQUIRES_ACTION',
            ], 200),
        ]);

        config(['services.xendit.secret_key' => 'xnd_test_key']);
        $service = new XenditService();

        $result = $service->createRecurringPlan(['reference_id' => 'sub-1', 'amount' => 499]);

        $this->assertSame('repl_test_001', $result['id']);
    }

    public function test_create_recurring_plan_throws_runtime_exception_on_failure(): void
    {
        Http::fake([
            'https://api.xendit.co/recurring/plans' => Http::response([], 500),
        ]);

        config(['services.xendit.secret_key' => 'xnd_test_key']);
        $service = new XenditService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to create Xendit recurring plan/');

        $service->createRecurringPlan(['amount' => 499]);
    }

    // ─── getRecurringPlan ────────────────────────────────────────────────────

    public function test_get_recurring_plan_returns_response_on_success(): void
    {
        Http::fake([
            'https://api.xendit.co/recurring/plans/repl_abc' => Http::response([
                'id'     => 'repl_abc',
                'status' => 'ACTIVE',
            ], 200),
        ]);

        config(['services.xendit.secret_key' => 'xnd_test_key']);
        $service = new XenditService();

        $result = $service->getRecurringPlan('repl_abc');

        $this->assertSame('repl_abc', $result['id']);
        $this->assertSame('ACTIVE', $result['status']);
    }

    public function test_get_recurring_plan_throws_runtime_exception_on_failure(): void
    {
        Http::fake([
            'https://api.xendit.co/recurring/plans/bad_id' => Http::response([], 404),
        ]);

        config(['services.xendit.secret_key' => 'xnd_test_key']);
        $service = new XenditService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to fetch Xendit recurring plan/');

        $service->getRecurringPlan('bad_id');
    }

    // ─── deactivateRecurringPlan ─────────────────────────────────────────────

    public function test_deactivate_recurring_plan_returns_response_on_success(): void
    {
        Http::fake([
            'https://api.xendit.co/recurring/plans/repl_abc/deactivate' => Http::response([
                'id'     => 'repl_abc',
                'status' => 'INACTIVE',
            ], 200),
        ]);

        config(['services.xendit.secret_key' => 'xnd_test_key']);
        $service = new XenditService();

        $result = $service->deactivateRecurringPlan('repl_abc');

        $this->assertSame('repl_abc', $result['id']);
        $this->assertSame('INACTIVE', $result['status']);
    }

    public function test_deactivate_recurring_plan_throws_runtime_exception_on_failure(): void
    {
        Http::fake([
            'https://api.xendit.co/recurring/plans/bad_id/deactivate' => Http::response([], 404),
        ]);

        config(['services.xendit.secret_key' => 'xnd_test_key']);
        $service = new XenditService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to deactivate Xendit recurring plan/');

        $service->deactivateRecurringPlan('bad_id');
    }

    // ─── collectionFee ───────────────────────────────────────────────────────

    public function test_collection_fee_gcash(): void
    {
        $this->assertSame(11.50, XenditService::collectionFee('GCASH', 500));
    }

    public function test_collection_fee_maya(): void
    {
        $this->assertSame(9.0, XenditService::collectionFee('MAYA', 500));
        $this->assertSame(9.0, XenditService::collectionFee('PAYMAYA', 500));
    }

    public function test_collection_fee_grabpay(): void
    {
        $this->assertSame(10.0, XenditService::collectionFee('GRABPAY', 500));
    }

    public function test_collection_fee_credit_card(): void
    {
        // 500 * 0.032 + 10 = 26
        $this->assertSame(26.0, XenditService::collectionFee('CREDIT_CARD', 500));
    }

    public function test_collection_fee_qrph_with_minimum(): void
    {
        // 500 * 0.014 = 7.0, min 15
        $this->assertSame(15.0, XenditService::collectionFee('QRPH', 500));
        // 2000 * 0.014 = 28, above min
        $this->assertSame(28.0, XenditService::collectionFee('QRPH', 2000));
    }

    public function test_collection_fee_direct_debit_with_minimum(): void
    {
        // 500 * 0.01 = 5.0, min 25
        $this->assertSame(25.0, XenditService::collectionFee('DD_BPI', 500));
        // 5000 * 0.01 = 50, above min
        $this->assertSame(50.0, XenditService::collectionFee('BPI', 5000));
    }

    public function test_collection_fee_unknown_channel_returns_zero(): void
    {
        $this->assertSame(0.0, XenditService::collectionFee('UNKNOWN', 1000));
    }
}
