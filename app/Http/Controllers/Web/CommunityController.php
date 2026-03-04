<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\CreateCommunity;
use App\Actions\Community\JoinCommunity;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCommunityRequest;
use App\Models\Community;
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

        $membership = $community->members()->where('user_id', auth()->id())->first();

        return Inertia::render('Communities/Show', compact('community', 'membership'));
    }

    public function members(Community $community): Response
    {
        $members = $community->members()->with('user')->paginate(20);

        return Inertia::render('Communities/Members', compact('community', 'members'));
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
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category'    => ['nullable', 'string', 'in:Tech,Business,Design,Health,Education,Finance,Other'],
            'avatar'       => ['nullable', 'url', 'max:500'],
            'cover_image'  => ['nullable', 'image', 'max:5120'],
            'price'        => ['nullable', 'numeric', 'min:0'],
            'currency'     => ['nullable', 'string', 'in:PHP,USD'],
            'is_private'   => ['boolean'],
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

        return Inertia::render('Communities/Analytics', [
            'community' => $community,
            'stats' => [
                'monthly_revenue'      => $monthlyRevenue,
                'active_subscriptions' => $activeCount,
                'total_members'        => $totalMembers,
                'free_members'         => $totalMembers - $activeCount,
            ],
            'subscribers' => $subscribers,
        ]);
    }

    public function join(Request $request, Community $community, JoinCommunity $action): RedirectResponse
    {
        $action->execute($request->user(), $community);

        return back()->with('success', 'You have joined the community!');
    }
}
