<?php

namespace App\Http\Controllers;

use App\Models\AffiliateConversion;
use App\Models\OwnerPayout;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class XenditWebhookController extends Controller
{
    public function payouts(Request $request): Response
    {
        // Verify callback token
        $token = $request->header('X-CALLBACK-TOKEN');
        if ($token !== config('services.xendit.callback_token')) {
            Log::warning('XenditWebhook: invalid callback token');
            return response('Unauthorized', 401);
        }

        $payload = $request->json()->all();
        $event   = $payload['event'] ?? null;
        $data    = $payload['data'] ?? [];

        Log::info('XenditWebhook: payout event received', ['event' => $event, 'reference_id' => $data['reference_id'] ?? null]);

        if (!in_array($event, ['payout.succeeded', 'payout.failed'])) {
            return response('OK', 200);
        }

        $xenditId    = $data['id'] ?? null;
        $referenceId = $data['reference_id'] ?? null;
        $status      = $event === 'payout.succeeded' ? 'succeeded' : 'failed';

        // ── Try to match an OwnerPayout ──────────────────────────────────────
        $ownerPayout = OwnerPayout::where('xendit_reference', $xenditId)
            ->orWhere('xendit_reference', $referenceId)
            ->first();

        if ($ownerPayout) {
            $ownerPayout->update(['status' => $status]);
            Log::info("XenditWebhook: OwnerPayout #{$ownerPayout->id} → {$status}");
            return response('OK', 200);
        }

        // ── Try to match an AffiliateConversion via reference_id pattern ─────
        // reference_id format: payout-{conversion_id}-{timestamp}
        if ($referenceId && str_starts_with($referenceId, 'payout-')) {
            $parts        = explode('-', $referenceId);
            $conversionId = $parts[1] ?? null;

            if ($conversionId) {
                $conversion = AffiliateConversion::find($conversionId);

                if ($conversion) {
                    if ($status === 'succeeded') {
                        $conversion->update(['status' => AffiliateConversion::STATUS_PAID, 'paid_at' => now()]);
                    } else {
                        $conversion->update(['status' => AffiliateConversion::STATUS_PENDING]);
                    }
                    Log::info("XenditWebhook: AffiliateConversion #{$conversion->id} → {$status}");
                    return response('OK', 200);
                }
            }
        }

        Log::warning('XenditWebhook: no matching record found', ['xendit_id' => $xenditId, 'reference_id' => $referenceId]);
        return response('OK', 200);
    }
}
