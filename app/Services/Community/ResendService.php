<?php

namespace App\Services\Community;

use App\Models\Community;
use Resend;
use Resend\Client;

class ResendService
{
    private Client $client;

    public function __construct(private readonly Community $community)
    {
        $apiKey = $community->resend_api_key;

        if (! $apiKey) {
            throw new \RuntimeException('No Resend API key configured for this community.');
        }

        $this->client = Resend::client($apiKey);
    }

    // ─── API Key Validation ──────────────────────────────────────────────────

    public function validateApiKey(): bool
    {
        try {
            $this->client->domains->list();

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    // ─── Domain Management ───────────────────────────────────────────────────

    public function addDomain(string $domain): \Resend\Domain
    {
        return $this->client->domains->create(['name' => $domain]);
    }

    public function getDomain(string $domainId): \Resend\Domain
    {
        return $this->client->domains->get($domainId);
    }

    public function verifyDomain(string $domainId): \Resend\Domain
    {
        return $this->client->domains->verify($domainId);
    }

    public function listDomains(): \Resend\Collection
    {
        return $this->client->domains->list();
    }

    // ─── Email Sending ───────────────────────────────────────────────────────

    public function sendEmail(array $params): \Resend\Email
    {
        return $this->client->emails->send($params);
    }

    /**
     * Send a batch of emails (max 100 per Resend API call).
     *
     * @param  array  $emails  Array of email payloads
     * @return \Resend\Collection<\Resend\Email>
     */
    public function sendBatch(array $emails): \Resend\Collection
    {
        return $this->client->batch->send($emails);
    }
}
