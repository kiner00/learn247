<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\EmailSend;
use App\Models\EmailUnsubscribe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResendWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        // Verify Resend webhook signing secret (Svix)
        $signingSecret = config('services.resend.webhook_secret');
        if ($signingSecret) {
            $signature = $request->header('svix-signature', '');
            $msgId = $request->header('svix-id', '');
            $timestamp = $request->header('svix-timestamp', '');
            $body = $request->getContent();

            $toSign = "{$msgId}.{$timestamp}.{$body}";
            $expected = base64_encode(hash_hmac('sha256', $toSign, base64_decode(str_replace('whsec_', '', $signingSecret)), true));

            // Svix sends multiple signatures separated by spaces (v1,<sig>)
            $valid = false;
            foreach (explode(' ', $signature) as $sig) {
                $parts = explode(',', $sig, 2);
                if (isset($parts[1]) && hash_equals($expected, $parts[1])) {
                    $valid = true;
                    break;
                }
            }

            if (! $valid) {
                Log::warning('ResendWebhook: invalid signature');

                return response()->json(['message' => 'invalid signature'], 401);
            }
        }

        $payload = $request->all();
        $type = $payload['type'] ?? null;
        $data = $payload['data'] ?? [];

        $emailId = $data['email_id'] ?? null;

        if (! $emailId) {
            return response()->json(['message' => 'ok']);
        }

        $send = EmailSend::where('resend_email_id', $emailId)->first();

        if (! $send) {
            Log::debug("ResendWebhook: unknown email_id {$emailId}");

            return response()->json(['message' => 'ok']);
        }

        match ($type) {
            'email.delivered' => $send->update(['status' => 'delivered']),

            'email.opened' => $send->update([
                'opened_at' => $send->opened_at ?? now(),
            ]),

            'email.clicked' => $send->update([
                'clicked_at' => $send->clicked_at ?? now(),
            ]),

            'email.bounced' => $this->handleBounce($send),

            'email.complained' => $this->handleComplaint($send),

            default => null,
        };

        return response()->json(['message' => 'ok']);
    }

    private function handleBounce(EmailSend $send): void
    {
        $send->update([
            'status' => 'bounced',
            'bounced_at' => now(),
        ]);

        // Auto-unsubscribe bounced addresses
        $member = $send->member()->with('user')->first();
        if ($member?->user) {
            EmailUnsubscribe::firstOrCreate([
                'community_id' => $send->community_id,
                'user_id' => $member->user->id,
            ], [
                'reason' => 'bounced',
                'unsubscribed_at' => now(),
            ]);
        }
    }

    private function handleComplaint(EmailSend $send): void
    {
        $send->update(['status' => 'complained']);

        $member = $send->member()->with('user')->first();
        if ($member?->user) {
            EmailUnsubscribe::firstOrCreate([
                'community_id' => $send->community_id,
                'user_id' => $member->user->id,
            ], [
                'reason' => 'complained',
                'unsubscribed_at' => now(),
            ]);
        }
    }
}
