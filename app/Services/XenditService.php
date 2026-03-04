<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XenditService
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
