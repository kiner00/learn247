<?php

namespace App\Services\Email;

use App\Contracts\EmailProvider;
use App\Models\Community;
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
        try {
            $this->client($community)->domains->list();

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
