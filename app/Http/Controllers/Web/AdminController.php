<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Subscription;
use App\Models\User;
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
            'byCategory'        => $byCategory,
            'recentCommunities' => $recentCommunities,
            'recentUsers'       => $recentUsers,
        ]);
    }
}
