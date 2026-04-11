<?php

namespace App\Http\Controllers;

use App\Actions\Affiliate\MarkAffiliateConversionPaid;
use App\Models\AffiliateConversion;
use App\Models\OwnerPayout;
use App\Models\PayoutRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class XenditWebhookController extends Controller
{
    public function payouts(Request $request): Response
    {
        // Verify callback token
        $token    = $request->header('X-CALLBACK-TOKEN');
        $expected = config('services.xendit.callback_token');
        Log::info('XenditWebhook: token check', ['received' => $token, 'expected' => $expected]);
        if (! $expected || ! $token || ! hash_equals($expected, $token)) {
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

        // ── Try to match an affiliate PayoutRequest via reference_id pattern ──
        // reference_id format: req-{payout_request_id}-{timestamp}
        if ($referenceId && str_starts_with($referenceId, 'req-')) {
            $parts          = explode('-', $referenceId);
            $payoutRequestId = $parts[1] ?? null;

            if ($payoutRequestId) {
                $payoutRequest = PayoutRequest::find($payoutRequestId);

                if ($payoutRequest && $payoutRequest->type === PayoutRequest::TYPE_OWNER) {
                    $newStatus = $status === 'succeeded' ? PayoutRequest::STATUS_PAID : PayoutRequest::STATUS_PENDING;
                    $payoutRequest->update(['status' => $newStatus]);
                    Log::info("XenditWebhook: owner PayoutRequest #{$payoutRequest->id} → {$newStatus}");
                    return response('OK', 200);
                }

                if ($payoutRequest && $payoutRequest->type === PayoutRequest::TYPE_AFFILIATE) {
                    if ($status === 'succeeded') {
                        $mark      = app(MarkAffiliateConversionPaid::class);
                        $remaining = (float) $payoutRequest->amount;

                        AffiliateConversion::where('affiliate_id', $payoutRequest->affiliate_id)
                            ->where('status', AffiliateConversion::STATUS_PENDING)
                            ->orderBy('created_at')
                            ->get()
                            ->each(function ($conversion) use (&$remaining, $mark) {
                                if ($remaining <= 0) return false;
                                $mark->execute($conversion);
                                $remaining -= (float) $conversion->commission_amount;
                            });

                        $payoutRequest->update(['status' => PayoutRequest::STATUS_PAID]);
                    } else {
                        $payoutRequest->update(['status' => PayoutRequest::STATUS_PENDING]);
                    }

                    Log::info("XenditWebhook: affiliate PayoutRequest #{$payoutRequest->id} → {$status}");
                    return response('OK', 200);
                }
            }
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
