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

        // Use a lightweight HTTP call to check the key.
        // Fetching a non-existent email returns 404 (valid key) vs 401/403 (invalid key).
        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->get('https://api.resend.com/emails/test_validation_check');

            // 401/403 = bad key, 404/422 = key is valid (resource not found is expected)
            return $response->status() !== 401 && $response->status() !== 403;
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
            'id'      => $result->id,
            'status'  => $result->status ?? 'pending',
            'records' => $result->records ?? [],
        ];
    }

    public function getDomain(Community $community, string $domainId): array
    {
        $result = $this->client($community)->domains->get($domainId);

        return [
            'id'      => $result->id,
            'name'    => $result->name,
            'status'  => $result->status,
            'records' => $result->records ?? [],
        ];
    }

    public function verifyDomain(Community $community, string $domainId): array
    {
        $result = $this->client($community)->domains->verify($domainId);

        return [
            'id'     => $result->id,
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
