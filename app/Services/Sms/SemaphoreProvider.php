<?php

namespace App\Services\Sms;

use App\Models\Community;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SemaphoreProvider implements SmsProviderInterface
{
    public function send(Community $community, array $numbers, string $message): array
    {
        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach (array_chunk($numbers, 100) as $chunk) {
            $payload = [
                'apikey' => $community->sms_api_key,
                'number' => implode(',', $chunk),
                'message' => $message,
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
                    $errors[] = 'Semaphore error: '.$response->body();
                    Log::error('SemaphoreProvider error', ['body' => $response->body()]);
                }
            } catch (\Throwable $e) {
                $failed += count($chunk);
                $errors[] = $e->getMessage();
                Log::error('SemaphoreProvider exception', ['error' => $e->getMessage()]);
            }
        }

        return compact('sent', 'failed', 'errors');
    }
}
