<?php

namespace App\Services\Email;

use App\Contracts\EmailProvider;
use App\Models\Community;
use Illuminate\Support\Facades\Http;
use Resend;

class ResendProvider implements EmailProvider
{
    public static function id(): string
    {
        return 'resend';
    }

    public static function label(): string
    {
        return 'Resend';
    }

    public function validateApiKey(Community $community): bool
    {
        $apiKey = $community->resend_api_key;

        if (! $apiKey) {
            return false;
        }

        // Attempt a dummy send — Resend returns different errors for valid vs invalid keys:
        // - Invalid key → 401 with message "API key is invalid"
        // - Valid send-only key → 403 with domain verification error (key works, just restricted)
        // - Valid full-access key → 422 or 403 validation error (key works)
        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->post('https://api.resend.com/emails', [
                    'from' => 'test@resend.dev',
                    'to' => ['validation-check@example.com'],
                    'subject' => 'key validation',
                    'html' => '<p>test</p>',
                ]);

            // 401 with "API key is invalid" = bad key
            if ($response->status() === 401) {
                $name = $response->json('name');
                $message = $response->json('message');

                // "restricted_api_key" on non-send endpoints means key is valid but limited
                // "API key is invalid" means truly invalid
                return $name === 'restricted_api_key' || ! str_contains($message ?? '', 'invalid');
            }

            // 403 = valid key but domain not verified (expected for validation)
            // 422 = valid key but validation error (also expected)
            // 200 = valid key and email sent (unlikely with test data but still valid)
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    public function sendEmail(Community $community, array $params): array
    {
        $result = $this->client($community)->emails->send($params);

        return ['id' => $result->id ?? null];
    }

    public function sendBatch(Community $community, array $emails): array
    {
        $result = $this->client($community)->batch->send($emails);
        $ids = [];

        foreach ($result->data ?? [] as $email) {
            $ids[] = ['id' => $email->id ?? null];
        }

        return $ids;
    }

    public function addDomain(Community $community, string $domain): array
    {
        $result = $this->client($community)->domains->create(['name' => $domain]);

        return [
            'id' => $result->id,
            'status' => $result->status ?? 'pending',
            'records' => $result->records ?? [],
        ];
    }

    public function getDomain(Community $community, string $domainId): array
    {
        $result = $this->client($community)->domains->get($domainId);

        return [
            'id' => $result->id,
            'name' => $result->name,
            'status' => $result->status,
            'records' => $result->records ?? [],
        ];
    }

    public function verifyDomain(Community $community, string $domainId): array
    {
        $result = $this->client($community)->domains->verify($domainId);

        return [
            'id' => $result->id,
            'status' => $result->status ?? 'pending',
        ];
    }

    private function client(Community $community): \Resend\Client
    {
        $apiKey = $community->resend_api_key;

        if (! $apiKey) {
            throw new \RuntimeException('No Resend API key configured.');
        }

        return Resend::client($apiKey);
    }
}
