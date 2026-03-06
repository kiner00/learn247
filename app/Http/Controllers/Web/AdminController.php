<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function dashboard(): Response
    {
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
        $totalPlatformFee     = round($affiliatePlatformFee + ($nonAffiliateGross * 0.03), 2);
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
}
