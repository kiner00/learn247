<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\CreateCommunity;
use App\Actions\Community\JoinCommunity;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCommunityRequest;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    public function index(): Response
    {
        $communities = Community::with('owner')
            ->withCount('members')
            ->latest()
            ->paginate(15);

        return Inertia::render('Communities/Index', compact('communities'));
    }

    public function store(CreateCommunityRequest $request, CreateCommunity $action): RedirectResponse
    {
        $community = $action->execute($request->user(), $request->validated());

        return redirect()->route('communities.show', $community->slug)
            ->with('success', 'Community created!');
    }

    public function show(Community $community): Response
    {
        $this->authorize('view', $community);

        $community->load(['owner', 'posts' => fn ($q) => $q->with(['author', 'comments.user'])->latest()->take(20)]);
        $community->loadCount('members');

        $userId     = auth()->id();
        $membership = $userId ? $community->members()->where('user_id', $userId)->first() : null;
        $affiliate  = $userId ? $community->affiliates()->where('user_id', $userId)->first() : null;

        return Inertia::render('Communities/Show', compact('community', 'membership', 'affiliate'));
    }

    public function members(Community $community, \Illuminate\Http\Request $request): Response
    {
        $query = $community->members()->with('user:id,name,username,bio');

        if ($request->filter === 'admin') {
            $query->where('role', 'admin');
        }

        $members    = $query->orderByRaw("FIELD(role, 'admin', 'moderator', 'member')")->paginate(20)->withQueryString();
        $totalCount = $community->members()->count();
        $adminCount = $community->members()->where('role', 'admin')->count();

        return Inertia::render('Communities/Members', compact('community', 'members', 'totalCount', 'adminCount'));
    }

    public function settings(Community $community): Response
    {
        $this->authorize('update', $community);

        return Inertia::render('Communities/Settings', compact('community'));
    }

    public function update(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        $data = $request->validate([
            'name'                     => ['required', 'string', 'max:255'],
            'description'              => ['nullable', 'string', 'max:2000'],
            'category'                 => ['nullable', 'string', 'in:Tech,Business,Design,Health,Education,Finance,Other'],
            'avatar'                   => ['nullable', 'url', 'max:500'],
            'cover_image'              => ['nullable', 'image', 'max:5120'],
            'price'                    => ['nullable', 'numeric', 'min:0'],
            'currency'                 => ['nullable', 'string', 'in:PHP,USD'],
            'is_private'               => ['boolean'],
            'affiliate_commission_rate' => ['nullable', 'integer', 'min:0', 'max:97'],
        ]);

        if ($request->hasFile('cover_image')) {
            // Delete old stored file if present
            if ($community->cover_image && str_starts_with($community->cover_image, '/storage/')) {
                Storage::disk('public')->delete(ltrim(str_replace('/storage/', '', $community->cover_image), '/'));
            }
            $path = $request->file('cover_image')->store('community-covers', 'public');
            $data['cover_image'] = Storage::url($path);
        } else {
            unset($data['cover_image']);
        }

        $community->update($data);

        return back()->with('success', 'Community updated.');
    }

    public function destroy(Community $community): RedirectResponse
    {
        $this->authorize('delete', $community);

        $community->delete();

        return redirect()->route('communities.index')->with('success', 'Community deleted.');
    }

    public function analytics(Community $community): Response
    {
        $this->authorize('viewAnalytics', $community);

        $activeCount = Subscription::where('community_id', $community->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->count();

        $monthlyRevenue = $activeCount * (float) $community->price;

        $totalMembers = $community->members()->count();

        $subscribers = Subscription::where('community_id', $community->id)
            ->with('user')
            ->latest()
            ->get()
            ->map(fn ($s) => [
                'id'         => $s->id,
                'user'       => ['name' => $s->user?->name, 'email' => $s->user?->email],
                'status'     => $s->status,
                'expires_at' => $s->expires_at?->toDateString(),
                'created_at' => $s->created_at?->toDateString(),
            ]);

        // Revenue breakdown from actual payments and affiliate conversions
        $grossRevenue = (float) Payment::where('community_id', $community->id)
            ->where('status', Payment::STATUS_PAID)
            ->sum('amount');

        $conversionBase = AffiliateConversion::whereHas(
            'affiliate', fn ($q) => $q->where('community_id', $community->id)
        );

        $affiliateGross        = (float) (clone $conversionBase)->sum('sale_amount');
        $affiliatePlatformFee  = (float) (clone $conversionBase)->sum('platform_fee');
        $affiliateCommission   = (float) (clone $conversionBase)->sum('commission_amount');
        $affiliateCreator      = (float) (clone $conversionBase)->sum('creator_amount');
        $affiliatePaid         = (float) (clone $conversionBase)->where('status', AffiliateConversion::STATUS_PAID)->sum('commission_amount');
        $affiliatePending      = (float) (clone $conversionBase)->where('status', AffiliateConversion::STATUS_PENDING)->sum('commission_amount');

        $nonAffiliateGross       = round($grossRevenue - $affiliateGross, 2);
        $nonAffiliatePlatformFee = round($nonAffiliateGross * 0.03, 2);
        $nonAffiliateCreator     = round($nonAffiliateGross - $nonAffiliatePlatformFee, 2);

        $totalPlatformFee  = round($affiliatePlatformFee + $nonAffiliatePlatformFee, 2);
        $totalCreatorNet   = round($affiliateCreator + $nonAffiliateCreator, 2);

        return Inertia::render('Communities/Analytics', [
            'community' => $community,
            'stats' => [
                'monthly_revenue'      => $monthlyRevenue,
                'active_subscriptions' => $activeCount,
                'total_members'        => $totalMembers,
                'free_members'         => $totalMembers - $activeCount,
            ],
            'revenue' => [
                'gross'                       => $grossRevenue,
                'platform_fee'                => $totalPlatformFee,
                'affiliate_commission_earned' => $affiliateCommission,
                'affiliate_commission_paid'   => $affiliatePaid,
                'affiliate_commission_pending' => $affiliatePending,
                'creator_net'                 => $totalCreatorNet,
                'has_affiliate_data'          => $affiliateGross > 0,
            ],
            'subscribers' => $subscribers,
        ]);
    }

    public function join(Request $request, Community $community, JoinCommunity $action): RedirectResponse
    {
        $action->execute($request->user(), $community);

        return back()->with('success', 'You have joined the community!');
    }

    public function about(Community $community): Response
    {
        $community->load('owner')->loadCount('members');

        return Inertia::render('Communities/About', compact('community'));
    }
}
