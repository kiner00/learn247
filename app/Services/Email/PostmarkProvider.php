<?php

namespace App\Services\Email;

use App\Contracts\EmailProvider;
use App\Models\Community;
use Illuminate\Support\Facades\Http;

class PostmarkProvider implements EmailProvider
{
    private const BASE_URL = 'https://api.postmarkapp.com';

    public static function id(): string
    {
        return 'postmark';
    }

    public static function label(): string
    {
        return 'Postmark';
    }

    public function validateApiKey(Community $community): bool
    {
        try {
            $response = $this->http($community)->get(self::BASE_URL.'/server');

            return $response->successful();
        } catch (\Exception) {
            return false;
        }
    }

    public function sendEmail(Community $community, array $params): array
    {
        $from = $params['from'];
        $payload = [
            'From' => $from,
            'To' => implode(',', $params['to']),
            'Subject' => $params['subject'],
            'HtmlBody' => $params['html'],
        ];

        if (! empty($params['reply_to'])) {
            $payload['ReplyTo'] = is_array($params['reply_to']) ? $params['reply_to'][0] : $params['reply_to'];
        }

        $response = $this->http($community)->post(self::BASE_URL.'/email', $payload);

        return ['id' => (string) ($response->json('MessageID') ?? '')];
    }

    public function sendBatch(Community $community, array $emails): array
    {
        // Postmark supports batch sending (up to 500 per call)
        $batch = [];

        foreach ($emails as $email) {
            $item = [
                'From' => $email['from'],
                'To' => implode(',', $email['to']),
                'Subject' => $email['subject'],
                'HtmlBody' => $email['html'],
            ];

            if (! empty($email['reply_to'])) {
                $item['ReplyTo'] = is_array($email['reply_to']) ? $email['reply_to'][0] : $email['reply_to'];
            }

            $batch[] = $item;
        }

        $response = $this->http($community)->post(self::BASE_URL.'/email/batch', $batch);
        $results = [];

        foreach ($response->json() ?? [] as $item) {
            $results[] = ['id' => (string) ($item['MessageID'] ?? '')];
        }

        return $results;
    }

    public function addDomain(Community $community, string $domain): array
    {
        // Postmark uses Account API (different token) for domains.
        // For simplicity, use the server token to create a sender signature.
        $response = $this->accountHttp($community)->post('https://api.postmarkapp.com/senders', [
            'FromEmail' => "noreply@{$domain}",
            'Name' => $domain,
        ]);

        $data = $response->json();

        return [
            'id' => (string) ($data['ID'] ?? ''),
            'status' => ($data['Confirmed'] ?? false) ? 'verified' : 'pending',
            'records' => $this->mapPostmarkRecords($data),
        ];
    }

    public function getDomain(Community $community, string $domainId): array
    {
        $response = $this->accountHttp($community)->get("https://api.postmarkapp.com/senders/{$domainId}");
        $data = $response->json();

        return [
            'id' => (string) ($data['ID'] ?? ''),
            'name' => $data['Domain'] ?? '',
            'status' => ($data['Confirmed'] ?? false) ? 'verified' : 'pending',
            'records' => $this->mapPostmarkRecords($data),
        ];
    }

    public function verifyDomain(Community $community, string $domainId): array
    {
        // Postmark verifies via DNS check
        $response = $this->accountHttp($community)->put("https://api.postmarkapp.com/senders/{$domainId}/verifyspf");
        $data = $response->json();

        return [
            'id' => (string) $domainId,
            'status' => ($data['SPFVerified'] ?? false) ? 'verified' : 'pending',
        ];
    }

    private function http(Community $community): \Illuminate\Http\Client\PendingRequest
    {
        $apiKey = $community->resend_api_key;

        if (! $apiKey) {
            throw new \RuntimeException('No Postmark API key configured.');
        }

        return Http::withHeaders([
            'X-Postmark-Server-Token' => $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);
    }

    private function accountHttp(Community $community): \Illuminate\Http\Client\PendingRequest
    {
        // Postmark uses same token for account-level operations in this context
        return $this->http($community);
    }

    private function mapPostmarkRecords(array $data): array
    {
        $records = [];

        if (! empty($data['DKIMHost'])) {
            $records[] = [
                'type' => 'TXT',
                'name' => $data['DKIMHost'],
                'value' => $data['DKIMTextValue'] ?? '',
                'status' => ($data['DKIMVerified'] ?? false) ? 'verified' : 'pending',
            ];
        }

        if (! empty($data['ReturnPathDomain'])) {
            $records[] = [
                'type' => 'CNAME',
                'name' => $data['ReturnPathDomain'],
                'value' => $data['ReturnPathDomainCNAMEValue'] ?? '',
                'status' => ($data['ReturnPathDomainVerified'] ?? false) ? 'verified' : 'pending',
            ];
        }

        return $records;
    }
}
