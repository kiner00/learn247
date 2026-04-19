<?php

namespace App\Services\Email;

use App\Contracts\EmailProvider;
use App\Models\Community;
use Illuminate\Support\Facades\Http;

class MailgunProvider implements EmailProvider
{
    private const BASE_URL = 'https://api.mailgun.net/v3';

    private const EU_URL = 'https://api.eu.mailgun.net/v3';

    public static function id(): string
    {
        return 'mailgun';
    }

    public static function label(): string
    {
        return 'Mailgun';
    }

    public function validateApiKey(Community $community): bool
    {
        try {
            $response = $this->http($community)->get(self::BASE_URL.'/domains');

            return $response->successful();
        } catch (\Exception) {
            return false;
        }
    }

    public function sendEmail(Community $community, array $params): array
    {
        $domain = $this->extractDomain($community);
        $from = $params['from'];

        $formData = [
            'from' => $from,
            'to' => implode(',', $params['to']),
            'subject' => $params['subject'],
            'html' => $params['html'],
        ];

        if (! empty($params['reply_to'])) {
            $formData['h:Reply-To'] = is_array($params['reply_to']) ? $params['reply_to'][0] : $params['reply_to'];
        }

        $response = $this->http($community)->asForm()->post(
            self::BASE_URL."/{$domain}/messages",
            $formData
        );

        return ['id' => $response->json('id')];
    }

    public function sendBatch(Community $community, array $emails): array
    {
        // Mailgun supports batch via recipient variables, but for simplicity
        // we send individually (matches the contract expectation)
        $results = [];

        foreach ($emails as $email) {
            try {
                $results[] = $this->sendEmail($community, $email);
            } catch (\Exception) {
                $results[] = ['id' => null];
            }
        }

        return $results;
    }

    public function addDomain(Community $community, string $domain): array
    {
        $response = $this->http($community)->asForm()->post(self::BASE_URL.'/domains', [
            'name' => $domain,
        ]);

        $data = $response->json();
        $records = $this->mapMailgunRecords($data);

        return [
            'id' => $domain,
            'status' => ($data['domain']['state'] ?? '') === 'active' ? 'verified' : 'pending',
            'records' => $records,
        ];
    }

    public function getDomain(Community $community, string $domainId): array
    {
        $response = $this->http($community)->get(self::BASE_URL."/domains/{$domainId}");
        $data = $response->json();

        return [
            'id' => $domainId,
            'name' => $data['domain']['name'] ?? $domainId,
            'status' => ($data['domain']['state'] ?? '') === 'active' ? 'verified' : 'pending',
            'records' => $this->mapMailgunRecords($data),
        ];
    }

    public function verifyDomain(Community $community, string $domainId): array
    {
        $response = $this->http($community)->put(self::BASE_URL."/domains/{$domainId}/verify");
        $data = $response->json();

        return [
            'id' => $domainId,
            'status' => ($data['domain']['state'] ?? '') === 'active' ? 'verified' : 'pending',
        ];
    }

    private function http(Community $community): \Illuminate\Http\Client\PendingRequest
    {
        $apiKey = $community->resend_api_key;

        if (! $apiKey) {
            throw new \RuntimeException('No Mailgun API key configured.');
        }

        return Http::withBasicAuth('api', $apiKey)->acceptJson();
    }

    private function extractDomain(Community $community): string
    {
        // Use the verified domain from the community's from email
        $fromEmail = $community->resend_from_email;

        if ($fromEmail && str_contains($fromEmail, '@')) {
            return substr($fromEmail, strpos($fromEmail, '@') + 1);
        }

        throw new \RuntimeException('From email must be set to determine Mailgun domain.');
    }

    private function mapMailgunRecords(array $data): array
    {
        $records = [];

        foreach (['sending_dns_records', 'receiving_dns_records'] as $key) {
            foreach ($data[$key] ?? [] as $record) {
                $records[] = [
                    'type' => $record['record_type'] ?? $record['type'] ?? '',
                    'name' => $record['name'] ?? '',
                    'value' => $record['value'] ?? '',
                    'status' => ($record['valid'] ?? '') === 'valid' ? 'verified' : 'pending',
                ];
            }
        }

        return $records;
    }
}
