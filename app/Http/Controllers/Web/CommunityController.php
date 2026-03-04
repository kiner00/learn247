<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\CreateCommunity;
use App\Actions\Community\JoinCommunity;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCommunityRequest;
use App\Models\Community;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $community->load(['owner', 'posts' => fn ($q) => $q->with('author')->latest()->take(20)]);
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

    public function join(Request $request, Community $community, JoinCommunity $action): RedirectResponse
    {
        $action->execute($request->user(), $community);

        return back()->with('success', 'You have joined the community!');
    }
}
