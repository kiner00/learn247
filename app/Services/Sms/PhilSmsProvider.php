<?php

namespace App\Services\Sms;

use App\Models\Community;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PhilSmsProvider implements SmsProviderInterface
{
    public function send(Community $community, array $numbers, string $message): array
    {
        $sent   = 0;
        $failed = 0;
        $errors = [];

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
                    Log::error('PhilSmsProvider error', ['body' => $response->body()]);
                }
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = $e->getMessage();
                Log::error('PhilSmsProvider exception', ['error' => $e->getMessage()]);
            }
        }

        return compact('sent', 'failed', 'errors');
    }
}
