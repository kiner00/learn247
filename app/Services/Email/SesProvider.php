<?php

namespace App\Services\Email;

use App\Contracts\EmailProvider;
use App\Models\Community;

class SesProvider implements EmailProvider
{
    public static function id(): string
    {
        return 'ses';
    }

    public static function label(): string
    {
        return 'Amazon SES';
    }

    public function validateApiKey(Community $community): bool
    {
        try {
            $client = $this->client($community);
            $client->getAccount();

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    public function sendEmail(Community $community, array $params): array
    {
        $client = $this->client($community);
        $from = $params['from'];

        $payload = [
            'FromEmailAddress' => $from,
            'Destination' => ['ToAddresses' => $params['to']],
            'Content' => [
                'Simple' => [
                    'Subject' => ['Data' => $params['subject'], 'Charset' => 'UTF-8'],
                    'Body' => ['Html' => ['Data' => $params['html'], 'Charset' => 'UTF-8']],
                ],
            ],
        ];

        if (! empty($params['reply_to'])) {
            $replyTo = is_array($params['reply_to']) ? $params['reply_to'] : [$params['reply_to']];
            $payload['ReplyToAddresses'] = $replyTo;
        }

        $result = $client->sendEmail($payload);

        return ['id' => $result['MessageId'] ?? null];
    }

    public function sendBatch(Community $community, array $emails): array
    {
        // SES v2 doesn't have a batch endpoint — send individually
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
        $client = $this->client($community);

        $result = $client->createEmailIdentity([
            'EmailIdentity' => $domain,
        ]);

        $records = [];
        foreach ($result['DkimAttributes']['Tokens'] ?? [] as $token) {
            $records[] = [
                'type' => 'CNAME',
                'name' => "{$token}._domainkey.{$domain}",
                'value' => "{$token}.dkim.amazonses.com",
                'status' => 'pending',
            ];
        }

        return [
            'id' => $domain,
            'status' => ($result['DkimAttributes']['Status'] ?? '') === 'SUCCESS' ? 'verified' : 'pending',
            'records' => $records,
        ];
    }

    public function getDomain(Community $community, string $domainId): array
    {
        $client = $this->client($community);

        $result = $client->getEmailIdentity([
            'EmailIdentity' => $domainId,
        ]);

        $records = [];
        foreach ($result['DkimAttributes']['Tokens'] ?? [] as $token) {
            $records[] = [
                'type' => 'CNAME',
                'name' => "{$token}._domainkey.{$domainId}",
                'value' => "{$token}.dkim.amazonses.com",
                'status' => ($result['DkimAttributes']['Status'] ?? '') === 'SUCCESS' ? 'verified' : 'pending',
            ];
        }

        return [
            'id' => $domainId,
            'name' => $domainId,
            'status' => ($result['DkimAttributes']['Status'] ?? '') === 'SUCCESS' ? 'verified' : 'pending',
            'records' => $records,
        ];
    }

    public function verifyDomain(Community $community, string $domainId): array
    {
        // SES auto-verifies via DNS. Just re-fetch status.
        $info = $this->getDomain($community, $domainId);

        return [
            'id' => $domainId,
            'status' => $info['status'],
        ];
    }

    private function client(Community $community): \Aws\SesV2\SesV2Client
    {
        $apiKey = $community->resend_api_key;

        if (! $apiKey) {
            throw new \RuntimeException('No AWS SES credentials configured.');
        }

        // API key format: "ACCESS_KEY_ID:SECRET_ACCESS_KEY:REGION"
        $parts = explode(':', $apiKey);

        if (count($parts) < 3) {
            throw new \RuntimeException('AWS SES key must be in format: ACCESS_KEY_ID:SECRET_ACCESS_KEY:REGION');
        }

        return new \Aws\SesV2\SesV2Client([
            'version' => 'latest',
            'region' => $parts[2],
            'credentials' => [
                'key' => $parts[0],
                'secret' => $parts[1],
            ],
        ]);
    }
}
