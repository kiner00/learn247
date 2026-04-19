<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Admin\ApprovePayoutRequest;
use App\Actions\Admin\BatchPayAffiliates;
use App\Actions\Admin\RejectPayoutRequest;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\PayoutRequest;
use App\Queries\Admin\GetPayoutsDashboard;
use App\Services\Payout\OwnerPayoutDispatcher;
use App\Services\XenditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayoutController extends Controller
{
    public function index(XenditService $xendit, GetPayoutsDashboard $query): Response
    {
        return Inertia::render('Admin/Payouts', array_merge(
            $query->execute(),
            ['xenditBalance' => $xendit->getBalance()]
        ));
    }

    public function payOwner(Community $community, OwnerPayoutDispatcher $dispatcher): RedirectResponse
    {
        abort_unless($dispatcher->canDispatch($community), 422, 'Owner has no payout details set.');

        try {
            $result = $dispatcher->dispatch($community);

            return back()->with('success', 'Paid ₱'.number_format($result['amount'], 2)." to {$community->owner->name} via Xendit.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function batchPayOwners(OwnerPayoutDispatcher $dispatcher): RedirectResponse
    {
        $result = $dispatcher->batchDispatch(
            Community::with('owner')->where('price', '>', 0)
        );

        return back()->with($result['errors'] ? 'error' : 'success', $result['message']);
    }

    public function paySelectedOwners(Request $request, OwnerPayoutDispatcher $dispatcher): RedirectResponse
    {
        $ids = $request->validate(['community_ids' => 'required|array', 'community_ids.*' => 'integer'])['community_ids'];

        $result = $dispatcher->batchDispatch(
            Community::with('owner')->whereIn('id', $ids)->get(),
            'selected owner(s)'
        );

        return back()->with($result['errors'] ? 'error' : 'success', $result['message']);
    }

    public function paySelectedAffiliates(Request $request, BatchPayAffiliates $action): RedirectResponse
    {
        $ids = $request->validate(['affiliate_ids' => 'required|array', 'affiliate_ids.*' => 'integer'])['affiliate_ids'];
        $result = $action->execute(affiliateIds: $ids);

        return back()->with($result['errors'] ? 'error' : 'success', $result['message']);
    }

    public function batchPayAffiliates(BatchPayAffiliates $action): RedirectResponse
    {
        $result = $action->execute();

        return back()->with($result['errors'] ? 'error' : 'success', $result['message']);
    }

    public function approveRequest(PayoutRequest $payoutRequest, ApprovePayoutRequest $action): RedirectResponse
    {
        try {
            $action->execute($payoutRequest);

            return back()->with('success', 'Approved & sent ₱'.number_format($payoutRequest->amount, 2)." to {$payoutRequest->user->name}.");
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function rejectRequest(Request $request, PayoutRequest $payoutRequest, RejectPayoutRequest $action): RedirectResponse
    {
        $reason = $request->validate(['reason' => 'nullable|string|max:255'])['reason'] ?? null;
        $action->execute($payoutRequest, $reason);

        return back()->with('success', "Payout request #{$payoutRequest->id} rejected.");
    }

    public function markRequestPaid(PayoutRequest $payoutRequest): RedirectResponse
    {
        abort_unless(
            $payoutRequest->status === PayoutRequest::STATUS_APPROVED,
            422,
            'Only approved requests can be marked paid.'
        );

        if ($payoutRequest->type === PayoutRequest::TYPE_AFFILIATE) {
            $mark = app(\App\Actions\Affiliate\MarkAffiliateConversionPaid::class);
            $remaining = (float) $payoutRequest->amount;

            \App\Models\AffiliateConversion::where('affiliate_id', $payoutRequest->affiliate_id)
                ->where('status', \App\Models\AffiliateConversion::STATUS_PENDING)
                ->orderBy('created_at')
                ->get()
                ->each(function ($conversion) use (&$remaining, $mark) {
                    if ($remaining <= 0) {
                        return false;
                    }
                    $mark->execute($conversion);
                    $remaining -= (float) $conversion->commission_amount;
                });
        }

        $payoutRequest->update([
            'status' => PayoutRequest::STATUS_PAID,
            'processed_by' => auth()->id(),
        ]);

        return back()->with('success', "Payout request #{$payoutRequest->id} marked as paid and conversions settled.");
    }
}
