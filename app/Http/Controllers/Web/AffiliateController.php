<?php

namespace App\Http\Controllers\Web;

use App\Actions\Affiliate\DisbursePayout;
use App\Actions\Affiliate\JoinAffiliate;
use App\Actions\Affiliate\MarkAffiliateConversionPaid;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateController extends Controller
{
    /** GET /my-affiliates — user's own affiliate links + earnings */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $affiliates = Affiliate::where('user_id', $user->id)
            ->with('community')
            ->latest()
            ->get()
            ->map(fn ($a) => [
                'id'             => $a->id,
                'code'           => $a->code,
                'status'         => $a->status,
                'total_earned'   => $a->total_earned,
                'total_paid'     => $a->total_paid,
                'pending_amount'  => $a->pendingAmount(),
                'referral_url'    => url("/ref/{$a->code}"),
                'community'       => [
                    'name' => $a->community->name,
                    'slug' => $a->community->slug,
                ],
            ]);

        return Inertia::render('Affiliates/Index', [
            'affiliates'    => $affiliates,
            'payoutMethod'  => $user->payout_method,
            'payoutDetails' => $user->payout_details,
        ]);
    }

    /** POST /communities/{community}/affiliates — join as affiliate */
    public function store(Request $request, Community $community, JoinAffiliate $action): RedirectResponse
    {
        $action->execute($request->user(), $community);

        return back()->with('success', 'You are now an affiliate! Share your link to start earning.');
    }

    /** GET /communities/{community}/affiliates — owner dashboard */
    public function dashboard(Request $request, Community $community): Response
    {
        $this->authorize('update', $community);

        $affiliates = Affiliate::where('community_id', $community->id)
            ->with('user')
            ->latest()
            ->get()
            ->map(fn ($a) => [
                'id'             => $a->id,
                'code'           => $a->code,
                'status'         => $a->status,
                'total_earned'   => $a->total_earned,
                'total_paid'     => $a->total_paid,
                'pending_amount' => $a->pendingAmount(),
                'referral_url'   => url("/ref/{$a->code}"),
                'user'           => ['name' => $a->user->name, 'email' => $a->user->email],
                'payout_method'  => $a->user->payout_method,
                'payout_details' => $a->user->payout_details,
            ]);

        $conversions = AffiliateConversion::whereHas('affiliate', fn ($q) => $q->where('community_id', $community->id))
            ->with(['affiliate.user', 'referredUser'])
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'id'                => $c->id,
                'date'              => $c->created_at->format('M j, Y'),
                'referred_user'     => $c->referredUser->name,
                'affiliate_name'    => $c->affiliate->user->name,
                'sale_amount'       => $c->sale_amount,
                'platform_fee'      => $c->platform_fee,
                'commission_amount' => $c->commission_amount,
                'creator_amount'    => $c->creator_amount,
                'status'            => $c->status,
                'paid_at'           => $c->paid_at?->format('M j, Y'),
                'payout_method'     => $c->affiliate->user->payout_method,
                'can_disburse'      => DisbursePayout::supports($c->affiliate->user->payout_method ?? ''),
            ]);

        $stats = [
            'total_affiliates'    => $affiliates->count(),
            'total_commissions'   => $affiliates->sum('total_earned'),
            'total_paid_out'      => $affiliates->sum('total_paid'),
        ];

        return Inertia::render('Communities/Affiliates', compact('community', 'affiliates', 'conversions', 'stats'));
    }

    /** PATCH /affiliates/{affiliate}/payout — affiliate sets their payout details */
    public function updatePayout(Request $request, Affiliate $affiliate): RedirectResponse
    {
        abort_unless($affiliate->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'payout_method'  => ['required', 'string', 'in:gcash,bank,paypal,maya'],
            'payout_details' => ['required', 'string', 'max:255'],
        ]);

        $affiliate->update($data);

        return back()->with('success', 'Payout details saved.');
    }

    /** POST /affiliate-conversions/{conversion}/disburse — pay via Xendit + mark paid */
    public function disburse(AffiliateConversion $conversion, DisbursePayout $disburse, MarkAffiliateConversionPaid $mark): RedirectResponse
    {
        $this->authorize('update', $conversion->affiliate->community);

        if ($conversion->status === AffiliateConversion::STATUS_PAID) {
            return back()->with('error', 'Already paid.');
        }

        try {
            $disburse->execute($conversion);
            $mark->execute($conversion);
            return back()->with('success', 'Payout sent via Xendit and marked as paid.');
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Xendit disbursement failed: ' . $e->getMessage());
        }
    }

    /** PATCH /affiliate-conversions/{conversion}/paid */
    public function markPaid(AffiliateConversion $conversion, MarkAffiliateConversionPaid $action): RedirectResponse
    {
        $this->authorize('update', $conversion->affiliate->community);

        if ($conversion->status === AffiliateConversion::STATUS_PAID) {
            return back()->with('error', 'Already marked as paid.');
        }

        $action->execute($conversion);

        return back()->with('success', 'Conversion marked as paid.');
    }
}
