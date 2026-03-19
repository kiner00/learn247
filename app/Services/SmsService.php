<?php

namespace App\Services;

use App\Models\Community;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public const PROVIDER_SEMAPHORE = 'semaphore';
    public const PROVIDER_XTREME    = 'xtreme_sms';
    public const PROVIDER_PHILSMS   = 'philsms';

    public const PROVIDERS = [
        self::PROVIDER_SEMAPHORE => 'Semaphore',
        self::PROVIDER_PHILSMS   => 'PhilSMS',
        self::PROVIDER_XTREME    => 'Xtreme SMS',
    ];

    /**
     * Send SMS to multiple numbers using the community's configured provider.
     *
     * @param  Community  $community
     * @param  array      $numbers   E.164 format, e.g. ['639171234567', ...]
     * @param  string     $message
     * @return array{sent: int, failed: int, errors: array}
     */
    public function blast(Community $community, array $numbers, string $message): array
    {
        if (empty($numbers)) {
            return ['sent' => 0, 'failed' => 0, 'errors' => []];
        }

        return match ($community->sms_provider) {
            self::PROVIDER_SEMAPHORE => $this->sendViaSemaphore($community, $numbers, $message),
            self::PROVIDER_PHILSMS   => $this->sendViaPhilSms($community, $numbers, $message),
            self::PROVIDER_XTREME    => $this->sendViaXtremeSms($community, $numbers, $message),
            default                  => throw new \RuntimeException('No SMS provider configured.'),
        };
    }

    // ─── Semaphore ────────────────────────────────────────────────────────────

    private function sendViaSemaphore(Community $community, array $numbers, string $message): array
    {
        $sent   = 0;
        $failed = 0;
        $errors = [];

        // Semaphore accepts up to 1000 recipients per request as comma-separated
        foreach (array_chunk($numbers, 100) as $chunk) {
            $payload = [
                'apikey'     => $community->sms_api_key,
                'number'     => implode(',', $chunk),
                'message'    => $message,
            ];

            if ($community->sms_sender_name) {
                $payload['sendername'] = $community->sms_sender_name;
            }

            try {
                $response = Http::timeout(30)
                    ->post('https://api.semaphore.co/api/v4/messages', $payload);

                if ($response->successful()) {
                    $sent += count($chunk);
                } else {
                    $failed += count($chunk);
                    $errors[] = 'Semaphore error: ' . $response->body();
                    Log::error('SmsService Semaphore error', ['body' => $response->body()]);
                }
            } catch (\Throwable $e) {
                $failed += count($chunk);
                $errors[] = $e->getMessage();
                Log::error('SmsService Semaphore exception', ['error' => $e->getMessage()]);
            }
        }

        return compact('sent', 'failed', 'errors');
    }

    // ─── PhilSMS ──────────────────────────────────────────────────────────────

    private function sendViaPhilSms(Community $community, array $numbers, string $message): array
    {
        $sent   = 0;
        $failed = 0;
        $errors = [];

        // PhilSMS sends one recipient per request
        foreach ($numbers as $number) {
            $payload = [
                'recipient' => $number,
                'message'   => $message,
                'type'      => 'plain',
            ];

            if ($community->sms_sender_name) {
                $payload['sender_id'] = $community->sms_sender_name;
            }

            try {
                $response = Http::timeout(30)
                    ->withToken($community->sms_api_key)
                    ->post('https://app.philsms.com/api/v3/sms/send', $payload);

                $body   = $response->json();
                $status = $body['status'] ?? null;

                if ($response->successful() && $status === 'success') {
                    $sent++;
                } else {
                    $failed++;
                    $errors[] = "PhilSMS error for {$number}: " . ($body['message'] ?? $response->body());
                    Log::error('SmsService PhilSMS error', ['body' => $response->body()]);
                }
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = $e->getMessage();
                Log::error('SmsService PhilSMS exception', ['error' => $e->getMessage()]);
            }
        }

        return compact('sent', 'failed', 'errors');
    }

    // ─── Xtreme SMS ───────────────────────────────────────────────────────────

    private function sendViaXtremeSms(Community $community, array $numbers, string $message): array
    {
        $sent   = 0;
        $failed = 0;
        $errors = [];

        $baseUrl = rtrim($community->sms_device_url ?? '', '/');

        if (! $baseUrl) {
            return ['sent' => 0, 'failed' => count($numbers), 'errors' => ['Xtreme SMS server URL not set.']];
        }

        $url = "{$baseUrl}/services/send.php";

        // Build messages array as required by the Xtreme SMS bulk API
        $messages = array_map(fn ($n) => ['number' => $n, 'message' => $message], $numbers);

        // Chunk to avoid oversized requests
        foreach (array_chunk($messages, 100) as $chunk) {
            try {
                $response = Http::timeout(60)
                    ->asForm()
                    ->post($url, [
                        'key'      => $community->sms_api_key,
                        'messages' => json_encode($chunk),
                        'option'   => 1, // USE_ALL_DEVICES — use all available devices
                    ]);

                if ($response->successful()) {
                    $json = $response->json();
                    if ($json['success'] ?? false) {
                        $sent += count($chunk);
                    } else {
                        $failed += count($chunk);
                        $msg     = $json['error']['message'] ?? $response->body();
                        $errors[] = "Xtreme SMS error: {$msg}";
                        Log::error('SmsService XtremeSMS error', ['body' => $response->body()]);
                    }
                } else {
                    $failed += count($chunk);
                    $errors[] = 'Xtreme SMS HTTP error: ' . $response->status();
                    Log::error('SmsService XtremeSMS HTTP error', ['status' => $response->status()]);
                }
            } catch (\Throwable $e) {
                $failed += count($chunk);
                $errors[] = $e->getMessage();
                Log::error('SmsService XtremeSMS exception', ['error' => $e->getMessage()]);
            }
        }

        return compact('sent', 'failed', 'errors');
    }
}
