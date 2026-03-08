<?php

namespace App\Http\Controllers\Web;

use App\Actions\Affiliate\DisbursePayout;
use App\Actions\Affiliate\MarkAffiliateConversionPaid;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function dashboard(XenditService $xendit): Response
    {
        $xenditBalance = $xendit->getBalance();
        $totalUsers          = User::count();
        $totalCommunities    = Community::count();
        $totalMembers        = CommunityMember::count();
        $activeSubscriptions = Subscription::where('status', Subscription::STATUS_ACTIVE)->count();

        // Monthly revenue: sum community.price for each active subscription
        $monthlyRevenue = Subscription::where('subscriptions.status', Subscription::STATUS_ACTIVE)
            ->join('communities', 'subscriptions.community_id', '=', 'communities.id')
            ->sum('communities.price');

        // Platform-wide gross revenue from actual payments
        $grossRevenue = (float) Payment::where('status', Payment::STATUS_PAID)->sum('amount');

        // Affiliate conversion totals
        $convTotals = AffiliateConversion::selectRaw(
            'SUM(sale_amount) as gross, SUM(platform_fee) as platform_fee,
             SUM(commission_amount) as commission, SUM(creator_amount) as creator'
        )->first();

        $affiliateGross          = (float) ($convTotals->gross ?? 0);
        $affiliatePlatformFee    = (float) ($convTotals->platform_fee ?? 0);
        $totalAffiliateCommission = (float) ($convTotals->commission ?? 0);
        $paidAffiliateCommission  = (float) AffiliateConversion::where('status', AffiliateConversion::STATUS_PAID)->sum('commission_amount');
        $pendingAffiliateCommission = round($totalAffiliateCommission - $paidAffiliateCommission, 2);

        $nonAffiliateGross    = round($grossRevenue - $affiliateGross, 2);
        $totalPlatformFee     = round($affiliatePlatformFee + ($nonAffiliateGross * 0.15), 2);
        $totalCreatorNet      = round($grossRevenue - $totalPlatformFee - $totalAffiliateCommission, 2);

        // Communities by category
        $byCategory = Community::selectRaw("COALESCE(category, 'Uncategorized') as category, COUNT(*) as total")
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        // Recent communities
        $recentCommunities = Community::with('owner')
            ->withCount('members')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($c) => [
                'id'            => $c->id,
                'name'          => $c->name,
                'slug'          => $c->slug,
                'category'      => $c->category,
                'members_count' => $c->members_count,
                'price'         => $c->price,
                'owner'         => ['name' => $c->owner?->name],
                'created_at'    => $c->created_at?->toDateString(),
            ]);

        // Recent users
        $recentUsers = User::latest()
            ->take(5)
            ->get()
            ->map(fn ($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'created_at' => $u->created_at?->toDateString(),
            ]);

        return Inertia::render('Admin/Dashboard', [
            'xenditBalance' => $xenditBalance,
            'stats' => [
                'total_users'          => $totalUsers,
                'total_communities'    => $totalCommunities,
                'total_members'        => $totalMembers,
                'active_subscriptions' => $activeSubscriptions,
                'monthly_revenue'      => (float) $monthlyRevenue,
            ],
            'revenue' => [
                'gross'                        => $grossRevenue,
                'platform_fee'                 => $totalPlatformFee,
                'creator_net'                  => $totalCreatorNet,
                'affiliate_commission_total'   => $totalAffiliateCommission,
                'affiliate_commission_paid'    => $paidAffiliateCommission,
                'affiliate_commission_pending' => $pendingAffiliateCommission,
            ],
            'byCategory'        => $byCategory,
            'recentCommunities' => $recentCommunities,
            'recentUsers'       => $recentUsers,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $request->validate(['app_theme' => 'required|in:green,yellow']);
        Setting::set('app_theme', $request->app_theme);
        return back()->with('success', 'Theme updated.');
    }

    public function payouts(XenditService $xendit): Response
    {
        $xenditBalance = $xendit->getBalance();
        // ── Community Owners ─────────────────────────────────────────────────
        $owners = Community::with('owner')
            ->where('price', '>', 0)
            ->get()
            ->groupBy('owner_id')
            ->map(function ($communities) {
                $owner = $communities->first()->owner;

                $rows = $communities->map(function ($community) {
                    $gross              = (float) Payment::where('community_id', $community->id)->where('status', Payment::STATUS_PAID)->sum('amount');
                    $affiliateCommission = (float) AffiliateConversion::whereHas('affiliate', fn ($q) => $q->where('community_id', $community->id))->sum('commission_amount');
                    $platformFee        = round($gross * 0.15, 2);
                    $earned             = round($gross - $platformFee - $affiliateCommission, 2);
                    $paid               = (float) OwnerPayout::where('community_id', $community->id)->where('status', '!=', 'failed')->sum('amount');
                    $pending            = round($earned - $paid, 2);

                    return [
                        'community_id'   => $community->id,
                        'community_name' => $community->name,
                        'community_slug' => $community->slug,
                        'gross'          => $gross,
                        'platform_fee'   => $platformFee,
                        'commissions'    => $affiliateCommission,
                        'earned'         => $earned,
                        'paid'           => $paid,
                        'pending'        => max(0, $pending),
                    ];
                })->values();

                return [
                    'user_id'        => $owner->id,
                    'name'           => $owner->name,
                    'email'          => $owner->email,
                    'payout_method'  => $owner->payout_method,
                    'payout_details' => $owner->payout_details,
                    'can_disburse'   => in_array($owner->payout_method, ['gcash', 'maya']) && $owner->payout_details,
                    'total_earned'   => $rows->sum('earned'),
                    'total_paid'     => $rows->sum('paid'),
                    'total_pending'  => $rows->sum('pending'),
                    'communities'    => $rows,
                ];
            })
            ->filter(fn ($o) => $o['total_earned'] > 0)
            ->values();

        // ── Affiliates ───────────────────────────────────────────────────────
        $affiliates = Affiliate::with(['user', 'community'])
            ->get()
            ->map(fn ($a) => [
                'id'             => $a->id,
                'name'           => $a->user->name,
                'email'          => $a->user->email,
                'community_name' => $a->community->name,
                'total_earned'   => (float) $a->total_earned,
                'total_paid'     => (float) $a->total_paid,
                'pending'        => $a->pendingAmount(),
                'payout_method'  => $a->payout_method,
                'payout_details' => $a->payout_details,
                'can_disburse'   => DisbursePayout::supports($a->payout_method ?? ''),
            ])
            ->filter(fn ($a) => $a['total_earned'] > 0)
            ->values();

        // ── Summary stats ────────────────────────────────────────────────────
        $stats = [
            'owners_pending'     => $owners->sum('total_pending'),
            'affiliates_pending' => $affiliates->sum('pending'),
        ];

        return Inertia::render('Admin/Payouts', compact('owners', 'affiliates', 'stats', 'xenditBalance'));
    }

    public function payOwner(Community $community, XenditService $xendit): RedirectResponse
    {
        $owner = $community->owner;

        abort_unless(in_array($owner->payout_method, ['gcash', 'maya']) && $owner->payout_details, 422, 'Owner has no payout details set.');

        $gross              = (float) Payment::where('community_id', $community->id)->where('status', Payment::STATUS_PAID)->sum('amount');
        $affiliateCommission = (float) AffiliateConversion::whereHas('affiliate', fn ($q) => $q->where('community_id', $community->id))->sum('commission_amount');
        $platformFee        = round($gross * 0.15, 2);
        $earned             = round($gross - $platformFee - $affiliateCommission, 2);
        $paid               = (float) OwnerPayout::where('community_id', $community->id)->where('status', '!=', 'failed')->sum('amount');
        $pending            = round($earned - $paid, 2);

        if ($pending <= 0) {
            return back()->with('error', 'No pending amount for this community.');
        }

        $channelCode = $owner->payout_method === 'gcash' ? 'PH_GCASH' : 'PH_PAYMAYA';
        $referenceId = 'owner-' . $community->id . '-' . time();

        try {
            $result = $xendit->createPayout([
                'reference_id'       => $referenceId,
                'currency'           => 'PHP',
                'channel_code'       => $channelCode,
                'channel_properties' => [
                    'account_holder_name' => $owner->name,
                    'account_number'      => $owner->payout_details,
                ],
                'amount'      => $pending,
                'description' => "Owner earnings – {$community->name}",
            ]);

            OwnerPayout::create([
                'community_id'     => $community->id,
                'user_id'          => $owner->id,
                'amount'           => $pending,
                'status'           => 'accepted',
                'xendit_reference' => $result['id'] ?? $referenceId,
                'paid_at'          => now(),
            ]);

            return back()->with('success', "Paid ₱" . number_format($pending, 2) . " to {$owner->name} via Xendit.");
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Xendit payout failed: ' . $e->getMessage());
        }
    }

    public function batchPayOwners(XenditService $xendit): RedirectResponse
    {
        $communities = Community::with('owner')->where('price', '>', 0)->get();
        $paid = 0;
        $errors = [];

        foreach ($communities as $community) {
            $owner = $community->owner;
            if (!in_array($owner->payout_method, ['gcash', 'maya']) || !$owner->payout_details) continue;

            $gross              = (float) Payment::where('community_id', $community->id)->where('status', Payment::STATUS_PAID)->sum('amount');
            $affiliateCommission = (float) AffiliateConversion::whereHas('affiliate', fn ($q) => $q->where('community_id', $community->id))->sum('commission_amount');
            $platformFee        = round($gross * 0.15, 2);
            $earned             = round($gross - $platformFee - $affiliateCommission, 2);
            $paidOut            = (float) OwnerPayout::where('community_id', $community->id)->where('status', '!=', 'failed')->sum('amount');
            $pending            = round($earned - $paidOut, 2);

            if ($pending <= 0) continue;

            $channelCode = $owner->payout_method === 'gcash' ? 'PH_GCASH' : 'PH_PAYMAYA';
            $referenceId = 'owner-' . $community->id . '-' . time();

            try {
                $result = $xendit->createPayout([
                    'reference_id'       => $referenceId,
                    'currency'           => 'PHP',
                    'channel_code'       => $channelCode,
                    'channel_properties' => [
                        'account_holder_name' => $owner->name,
                        'account_number'      => $owner->payout_details,
                    ],
                    'amount'      => $pending,
                    'description' => "Owner earnings – {$community->name}",
                ]);

                OwnerPayout::create([
                    'community_id'     => $community->id,
                    'user_id'          => $owner->id,
                    'amount'           => $pending,
                    'status'           => 'accepted',
                    'xendit_reference' => $result['id'] ?? $referenceId,
                    'paid_at'          => now(),
                ]);
                $paid++;
            } catch (\RuntimeException $e) {
                $errors[] = "{$community->name}: " . $e->getMessage();
            }
        }

        $msg = "Paid {$paid} community owner(s).";
        if ($errors) $msg .= ' Errors: ' . implode('; ', $errors);

        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function paySelectedOwners(Request $request, XenditService $xendit): RedirectResponse
    {
        $ids = $request->validate(['community_ids' => 'required|array', 'community_ids.*' => 'integer'])['community_ids'];

        $communities = Community::with('owner')->whereIn('id', $ids)->get();
        $paid = 0;
        $errors = [];

        foreach ($communities as $community) {
            $owner = $community->owner;
            if (!in_array($owner->payout_method, ['gcash', 'maya']) || !$owner->payout_details) continue;

            $gross               = (float) Payment::where('community_id', $community->id)->where('status', Payment::STATUS_PAID)->sum('amount');
            $affiliateCommission = (float) AffiliateConversion::whereHas('affiliate', fn ($q) => $q->where('community_id', $community->id))->sum('commission_amount');
            $platformFee         = round($gross * 0.15, 2);
            $earned              = round($gross - $platformFee - $affiliateCommission, 2);
            $paidOut             = (float) OwnerPayout::where('community_id', $community->id)->where('status', '!=', 'failed')->sum('amount');
            $pending             = round($earned - $paidOut, 2);

            if ($pending <= 0) continue;

            $channelCode = $owner->payout_method === 'gcash' ? 'PH_GCASH' : 'PH_PAYMAYA';
            $referenceId = 'owner-' . $community->id . '-' . time();

            try {
                $result = $xendit->createPayout([
                    'reference_id'       => $referenceId,
                    'currency'           => 'PHP',
                    'channel_code'       => $channelCode,
                    'channel_properties' => [
                        'account_holder_name' => $owner->name,
                        'account_number'      => $owner->payout_details,
                    ],
                    'amount'      => $pending,
                    'description' => "Owner earnings – {$community->name}",
                ]);

                OwnerPayout::create([
                    'community_id'     => $community->id,
                    'user_id'          => $owner->id,
                    'amount'           => $pending,
                    'status'           => 'accepted',
                    'xendit_reference' => $result['id'] ?? $referenceId,
                    'paid_at'          => now(),
                ]);
                $paid++;
            } catch (\RuntimeException $e) {
                $errors[] = "{$community->name}: " . $e->getMessage();
            }
        }

        $msg = "Paid {$paid} selected owner(s).";
        if ($errors) $msg .= ' Errors: ' . implode('; ', $errors);

        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function paySelectedAffiliates(Request $request, DisbursePayout $disburse, MarkAffiliateConversionPaid $mark): RedirectResponse
    {
        $ids = $request->validate(['affiliate_ids' => 'required|array', 'affiliate_ids.*' => 'integer'])['affiliate_ids'];

        $conversions = AffiliateConversion::where('status', AffiliateConversion::STATUS_PENDING)
            ->whereIn('affiliate_id', $ids)
            ->with(['affiliate.user', 'affiliate.community'])
            ->get()
            ->filter(fn ($c) => DisbursePayout::supports($c->affiliate->payout_method ?? ''));

        $paid = 0;
        $errors = [];

        foreach ($conversions as $conversion) {
            try {
                $disburse->execute($conversion);
                $mark->execute($conversion);
                $paid++;
            } catch (\RuntimeException $e) {
                $errors[] = "Conversion #{$conversion->id}: " . $e->getMessage();
            }
        }

        $msg = "Paid {$paid} selected affiliate conversion(s).";
        if ($errors) $msg .= ' Errors: ' . implode('; ', $errors);

        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function batchPayAffiliates(DisbursePayout $disburse, MarkAffiliateConversionPaid $mark): RedirectResponse
    {
        $conversions = AffiliateConversion::where('status', AffiliateConversion::STATUS_PENDING)
            ->with(['affiliate.user', 'affiliate.community'])
            ->get()
            ->filter(fn ($c) => DisbursePayout::supports($c->affiliate->payout_method ?? ''));

        $paid = 0;
        $errors = [];

        foreach ($conversions as $conversion) {
            try {
                $disburse->execute($conversion);
                $mark->execute($conversion);
                $paid++;
            } catch (\RuntimeException $e) {
                $errors[] = "Conversion #{$conversion->id}: " . $e->getMessage();
            }
        }

        $msg = "Paid {$paid} affiliate conversion(s).";
        if ($errors) $msg .= ' Errors: ' . implode('; ', $errors);

        return back()->with($errors ? 'error' : 'success', $msg);
    }
}
