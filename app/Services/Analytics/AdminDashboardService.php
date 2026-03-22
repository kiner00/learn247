<?php

namespace App\Services\Analytics;

use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;

/**
 * Aggregates platform-wide stats for the admin dashboard.
 * Extracted from AdminController::dashboard() so any future admin API
 * endpoint can consume the same data.
 */
class AdminDashboardService
{
    public function build(): array
    {
        // ── Counts ────────────────────────────────────────────────────────────
        $totalUsers       = User::count();
        $totalCommunities = Community::count();
        $totalMembers     = CommunityMember::count();

        $activeSubscriptions = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->whereHas('payments', fn ($q) => $q->where('status', Payment::STATUS_PAID))
            ->count();

        $monthlyRevenue = Subscription::where('subscriptions.status', Subscription::STATUS_ACTIVE)
            ->whereHas('payments', fn ($q) => $q->where('status', Payment::STATUS_PAID))
            ->join('communities', 'subscriptions.community_id', '=', 'communities.id')
            ->sum('communities.price');

        // ── Revenue breakdown ─────────────────────────────────────────────────
        $grossRevenue = (float) Payment::where('status', Payment::STATUS_PAID)->sum('amount');

        $convTotals = AffiliateConversion::selectRaw(
            'SUM(sale_amount) as gross, SUM(platform_fee) as platform_fee,
             SUM(commission_amount) as commission, SUM(creator_amount) as creator'
        )->first();

        $affiliateGross           = (float) ($convTotals->gross ?? 0);
        $affiliatePlatformFee     = (float) ($convTotals->platform_fee ?? 0);
        $totalAffiliateCommission = (float) ($convTotals->commission ?? 0);
        $paidAffiliateCommission  = (float) AffiliateConversion::where('status', AffiliateConversion::STATUS_PAID)->sum('commission_amount');
        $pendingAffiliateCommission = round($totalAffiliateCommission - $paidAffiliateCommission, 2);

        $nonAffiliatePlatformFee = Community::with('owner')->get()->sum(function ($c) {
            $cGross    = (float) Payment::where('community_id', $c->id)->where('status', Payment::STATUS_PAID)->sum('amount');
            $cAffGross = (float) AffiliateConversion::whereHas('affiliate', fn ($q) => $q->where('community_id', $c->id))->sum('sale_amount');
            return max(0, $cGross - $cAffGross) * $c->platformFeeRate();
        });

        $totalPlatformFee = round($affiliatePlatformFee + $nonAffiliatePlatformFee, 2);
        $totalCreatorNet  = round($grossRevenue - $totalPlatformFee - $totalAffiliateCommission, 2);

        // ── Recent activity ───────────────────────────────────────────────────
        $byCategory = Community::selectRaw("COALESCE(category, 'Uncategorized') as category, COUNT(*) as total")
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

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
                'is_featured'   => (bool) $c->is_featured,
            ]);

        $recentUsers = User::latest()
            ->take(5)
            ->get()
            ->map(fn ($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'avatar'     => $u->avatar,
                'created_at' => $u->created_at?->toDateString(),
            ]);

        $pendingOnboarding = User::where('needs_password_setup', true)
            ->with(['communityMemberships.community:id,name,slug'])
            ->latest()
            ->paginate(15, ['*'], 'pending_page')
            ->through(fn ($u) => [
                'id'             => $u->id,
                'name'           => $u->name,
                'email'          => $u->email,
                'joined_at'      => $u->created_at?->toDateString(),
                'days_since'     => (int) $u->created_at?->diffInDays(now()),
                'community'      => $u->communityMemberships->first()?->community?->name,
                'community_slug' => $u->communityMemberships->first()?->community?->slug,
            ]);

        return [
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
            'pendingOnboarding' => $pendingOnboarding,
        ];
    }
}
