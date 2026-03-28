<?php

namespace App\Services\Sms;

use App\Models\Community;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XtremeSmsProvider implements SmsProviderInterface
{
    public function send(Community $community, array $numbers, string $message): array
    {
        $sent   = 0;
        $failed = 0;
        $errors = [];

        $baseUrl = rtrim($community->sms_device_url ?? '', '/');

        if (! $baseUrl) {
            return ['sent' => 0, 'failed' => count($numbers), 'errors' => ['Xtreme SMS server URL not set.']];
        }

        $url      = "{$baseUrl}/services/send.php";
        $messages = array_map(fn ($n) => ['number' => $n, 'message' => $message], $numbers);

        foreach (array_chunk($messages, 100) as $chunk) {
            try {
                $response = Http::timeout(60)
                    ->asForm()
                    ->post($url, [
                        'key'      => $community->sms_api_key,
                        'messages' => json_encode($chunk),
                        'option'   => 1,
                    ]);

                if ($response->successful()) {
                    $json = $response->json();
                    if ($json['success'] ?? false) {
                        $sent += count($chunk);
                    } else {
                        $failed += count($chunk);
                        $msg     = $json['error']['message'] ?? $response->body();
                        $errors[] = "Xtreme SMS error: {$msg}";
                        Log::error('XtremeSmsProvider error', ['body' => $response->body()]);
                    }
                } else {
                    $failed += count($chunk);
                    $errors[] = 'Xtreme SMS HTTP error: ' . $response->status();
                    Log::error('XtremeSmsProvider HTTP error', ['status' => $response->status()]);
                }
            } catch (\Throwable $e) {
                $failed += count($chunk);
                $errors[] = $e->getMessage();
                Log::error('XtremeSmsProvider exception', ['error' => $e->getMessage()]);
            }
        }

        return compact('sent', 'failed', 'errors');
    }
}
