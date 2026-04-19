<?php

namespace App\Services\Email;

use App\Contracts\EmailProvider;
use App\Models\Community;
use Illuminate\Support\Facades\Http;

class SendGridProvider implements EmailProvider
{
    private const BASE_URL = 'https://api.sendgrid.com/v3';

    public static function id(): string
    {
        return 'sendgrid';
    }

    public static function label(): string
    {
        return 'SendGrid';
    }

    public function validateApiKey(Community $community): bool
    {
        try {
            $response = $this->http($community)->get(self::BASE_URL.'/scopes');

            return $response->successful();
        } catch (\Exception) {
            return false;
        }
    }

    public function sendEmail(Community $community, array $params): array
    {
        $payload = $this->buildPayload($params);

        $response = $this->http($community)->post(self::BASE_URL.'/mail/send', $payload);

        return ['id' => $response->header('X-Message-Id')];
    }

    public function sendBatch(Community $community, array $emails): array
    {
        // SendGrid doesn't have a batch endpoint — send individually
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
        $response = $this->http($community)->post(self::BASE_URL.'/whitelabel/domains', [
            'domain' => $domain,
            'automatic_security' => true,
        ]);

        $data = $response->json();

        return [
            'id' => (string) ($data['id'] ?? ''),
            'status' => ($data['valid'] ?? false) ? 'verified' : 'pending',
            'records' => $this->mapSendGridRecords($data),
        ];
    }

    public function getDomain(Community $community, string $domainId): array
    {
        $response = $this->http($community)->get(self::BASE_URL."/whitelabel/domains/{$domainId}");
        $data = $response->json();

        return [
            'id' => (string) ($data['id'] ?? ''),
            'name' => $data['domain'] ?? '',
            'status' => ($data['valid'] ?? false) ? 'verified' : 'pending',
            'records' => $this->mapSendGridRecords($data),
        ];
    }

    public function verifyDomain(Community $community, string $domainId): array
    {
        $response = $this->http($community)->post(self::BASE_URL."/whitelabel/domains/{$domainId}/validate");
        $data = $response->json();

        return [
            'id' => (string) $domainId,
            'status' => ($data['valid'] ?? false) ? 'verified' : 'pending',
        ];
    }

    private function http(Community $community): \Illuminate\Http\Client\PendingRequest
    {
        $apiKey = $community->resend_api_key;

        if (! $apiKey) {
            throw new \RuntimeException('No SendGrid API key configured.');
        }

        return Http::withToken($apiKey)->acceptJson();
    }

    private function buildPayload(array $params): array
    {
        $from = $this->parseFrom($params['from']);

        $payload = [
            'personalizations' => [
                [
                    'to' => array_map(fn ($email) => ['email' => $email], $params['to']),
                ],
            ],
            'from' => $from,
            'subject' => $params['subject'],
            'content' => [
                ['type' => 'text/html', 'value' => $params['html']],
            ],
        ];

        if (! empty($params['reply_to'])) {
            $payload['reply_to'] = ['email' => is_array($params['reply_to']) ? $params['reply_to'][0] : $params['reply_to']];
        }

        return $payload;
    }

    private function parseFrom(string $from): array
    {
        if (preg_match('/^(.+?)\s*<(.+?)>$/', $from, $matches)) {
            return ['email' => trim($matches[2]), 'name' => trim($matches[1])];
        }

        return ['email' => $from];
    }

    private function mapSendGridRecords(array $data): array
    {
        $records = [];

        foreach (['dns' => $data['dns'] ?? []] as $dnsData) {
            foreach ($dnsData as $key => $record) {
                if (is_array($record) && isset($record['host'])) {
                    $records[] = [
                        'type' => $record['type'] ?? 'CNAME',
                        'name' => $record['host'] ?? '',
                        'value' => $record['data'] ?? '',
                        'status' => ($record['valid'] ?? false) ? 'verified' : 'pending',
                    ];
                }
            }
        }

        return $records;
    }
}
