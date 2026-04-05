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
        $payload = $request->all();
        $type    = $payload['type'] ?? null;
        $data    = $payload['data'] ?? [];

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
            'status'     => 'bounced',
            'bounced_at' => now(),
        ]);

        // Auto-unsubscribe bounced addresses
        $member = $send->member()->with('user')->first();
        if ($member?->user) {
            EmailUnsubscribe::firstOrCreate([
                'community_id' => $send->community_id,
                'user_id'      => $member->user->id,
            ], [
                'reason'          => 'bounced',
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
                'user_id'      => $member->user->id,
            ], [
                'reason'          => 'complained',
                'unsubscribed_at' => now(),
            ]);
        }
    }
}
