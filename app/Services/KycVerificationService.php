<?php

namespace App\Services;

use App\Ai\Agents\KycVerifier;
use App\Mail\KycResultMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Files\RemoteImage;

class KycVerificationService
{
    public function verify(User $user): array
    {
        $idUrl     = $user->kyc_id_document;
        $selfieUrl = $user->kyc_selfie;

        if (! $idUrl || ! $selfieUrl) {
            return ['approved' => false, 'reason' => 'Missing documents'];
        }

        // Convert relative storage URLs to full URLs
        $idUrl     = $this->toFullUrl($idUrl);
        $selfieUrl = $this->toFullUrl($selfieUrl);

        try {
            $agent = new KycVerifier();

            $response = $agent->prompt(
                'Please verify these KYC documents. Image 1 is the government ID. Image 2 is the selfie with ID.',
                attachments: [
                    new RemoteImage($idUrl),
                    new RemoteImage($selfieUrl),
                ],
            );

            $text = trim($response->text);

            // Strip markdown code fences if present
            $text = preg_replace('/^```(?:json)?\s*/', '', $text);
            $text = preg_replace('/\s*```$/', '', $text);

            $result = json_decode($text, true);

            if (! $result || ! isset($result['approved'])) {
                Log::warning('KYC AI verification returned invalid response', [
                    'user_id'  => $user->id,
                    'response' => $text,
                ]);

                return ['approved' => false, 'reason' => 'AI verification returned an invalid response. Manual review required.'];
            }

            Log::info('KYC AI verification result', [
                'user_id' => $user->id,
                'result'  => $result,
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error('KYC AI verification failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return ['approved' => false, 'reason' => 'AI verification failed. Manual review required.'];
        }
    }

    public function verifyAndUpdate(User $user): array
    {
        $result = $this->verify($user);

        // Store the AI result regardless of outcome
        $user->kyc_ai_result = $result;

        if ($result['approved'] ?? false) {
            $user->kyc_status          = User::KYC_APPROVED;
            $user->kyc_verified_at     = now();
            $user->kyc_rejected_reason = null;
        } else {
            $user->kyc_ai_rejections = ($user->kyc_ai_rejections ?? 0) + 1;
            $user->kyc_status        = User::KYC_REJECTED;
            $user->kyc_rejected_reason = $result['reason'] ?? 'AI verification failed';
        }

        $user->save();

        Mail::to($user)->queue(new KycResultMail(
            user: $user,
            approved: (bool) ($result['approved'] ?? false),
            reason: $result['reason'] ?? null,
        ));

        return $result;
    }

    private function toFullUrl(string $url): string
    {
        if (str_starts_with($url, 'http')) {
            return $url;
        }

        return rtrim(config('app.url'), '/') . $url;
    }
}
