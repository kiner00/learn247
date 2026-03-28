<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XenditService implements PaymentGateway
{
    private const BASE_URL = 'https://api.xendit.co';

    private string $secretKey;
    private string $callbackToken;

    public function __construct()
    {
        $this->secretKey     = config('services.xendit.secret_key') ?? '';
        $this->callbackToken = config('services.xendit.callback_token') ?? '';
    }

    /**
     * Create a Xendit Invoice (one-time payment link).
     * @throws \RuntimeException
     */
    public function createInvoice(array $data): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->post(self::BASE_URL . '/v2/invoices', $data);

        if ($response->failed()) {
            Log::error('XenditService::createInvoice failed', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);
            throw new \RuntimeException('Failed to create Xendit invoice: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Fetch a Xendit Invoice by ID.
     * @throws \RuntimeException
     */
    public function getInvoice(string $invoiceId): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->get(self::BASE_URL . "/v2/invoices/{$invoiceId}");

        if ($response->failed()) {
            throw new \RuntimeException('Failed to fetch Xendit invoice: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Create a Xendit Payout (send money to e-wallet or bank via /payouts API).
     * @throws \RuntimeException
     */
    public function createPayout(array $data): array
    {
        $idempotencyKey = $data['reference_id'] ?? uniqid('payout-');

        $response = Http::withBasicAuth($this->secretKey, '')
            ->withHeaders(['Idempotency-key' => $idempotencyKey])
            ->post(self::BASE_URL . '/v2/payouts', $data);

        if ($response->failed()) {
            Log::error('XenditService::createPayout failed', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);
            throw new \RuntimeException('Xendit payout failed: ' . ($response->json()['message'] ?? $response->body()));
        }

        return $response->json();
    }

    /**
     * Get Xendit account balance.
     * @param string $accountType CASH | HOLDING | TAX
     */
    public function getBalance(string $accountType = 'CASH'): float
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->get(self::BASE_URL . '/balance', ['account_type' => $accountType]);

        if ($response->failed()) {
            Log::error('XenditService::getBalance failed', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);
            return 0.0;
        }

        return (float) ($response->json('balance') ?? 0);
    }

    /**
     * Fetch a Xendit Payout by ID.
     */
    public function getPayout(string $payoutId): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->get(self::BASE_URL . "/v2/payouts/{$payoutId}");

        if ($response->failed()) {
            throw new \RuntimeException('Failed to fetch Xendit payout: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Calculate Xendit's collection fee for a given payment channel and gross amount.
     * Returns the fee amount in PHP so callers can store net = gross - fee.
     *
     * Rates sourced from Xendit PH pricing (as of 2026-03):
     *   GCash 2.3% | Maya 1.8% | GrabPay/ShopeePay 2.0% |
     *   Credit/Debit 3.2% + ₱10 | QRPH 1.4% min ₱15 | Direct Debit 1% min ₱25
     */
    public static function collectionFee(string $channel, float $gross): float
    {
        $channel = strtoupper($channel);

        return match (true) {
            $channel === 'GCASH'                          => round($gross * 0.023, 2),
            in_array($channel, ['PAYMAYA', 'MAYA'])       => round($gross * 0.018, 2),
            in_array($channel, ['GRABPAY', 'SHOPEEPAY'])  => round($gross * 0.020, 2),
            in_array($channel, ['CREDIT_CARD', 'DEBIT_CARD', 'VISA', 'MASTERCARD']) => round($gross * 0.032 + 10, 2),
            $channel === 'QRPH'                           => max(round($gross * 0.014, 2), 15.0),
            in_array($channel, ['DD_BPI', 'BPI', 'DD_UBP', 'UBP']) => max(round($gross * 0.010, 2), 25.0),
            default                                       => 0.0,
        };
    }

    /**
     * Verify the Xendit x-callback-token header using constant-time comparison.
     */
    public function verifyCallbackToken(?string $token): bool
    {
        if (empty($token) || empty($this->callbackToken)) {
            return false;
        }

        return hash_equals($this->callbackToken, $token);
    }
}
