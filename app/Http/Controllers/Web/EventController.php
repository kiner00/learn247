<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\ManageEvent;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request, Community $community): Response
    {
        $user    = $request->user();
        $isMember = $user && CommunityMember::where('community_id', $community->id)->where('user_id', $user->id)->exists();
        $isOwner  = $user && $user->id === $community->owner_id;

        $year  = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $from  = now()->setDate($year, $month, 1)->startOfDay();
        $to    = $from->copy()->endOfMonth()->endOfDay();

        $eventsQuery = $community->events()->whereBetween('start_at', [$from, $to]);
        if (! $isMember && ! $isOwner) {
            $eventsQuery->where('is_members_only', false);
        }

        $events = $eventsQuery->get()->map(fn (Event $e) => [
            'id'              => $e->id,
            'title'           => $e->title,
            'description'     => $e->description,
            'start_at'        => $e->start_at->toISOString(),
            'end_at'          => $e->end_at?->toISOString(),
            'timezone'        => $e->timezone,
            'url'             => $e->url,
            'cover_image'     => $e->cover_image ? Storage::url($e->cover_image) : null,
            'is_members_only' => $e->is_members_only,
        ]);

        return Inertia::render('Communities/Calendar', [
            'community'    => $community->only('id', 'name', 'slug', 'avatar', 'cover_image'),
            'membership'   => $isMember || $isOwner ? ['role' => $isOwner ? 'owner' : 'member'] : null,
            'events'       => $events,
            'year'         => $year,
            'month'        => $month,
            'isOwner'      => $isOwner,
            'userTimezone' => $user?->timezone ?? 'UTC',
        ]);
    }

    public function store(Request $request, Community $community, ManageEvent $action): RedirectResponse
    {
        $this->authorizeOwner($request, $community);

        $data = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string|max:5000',
            'start_at'        => 'required|date',
            'end_at'          => 'nullable|date|after:start_at',
            'timezone'        => 'required|string|timezone',
            'url'             => 'nullable|url|max:500',
            'cover_image'     => 'nullable|image|max:10240',
            'is_members_only' => 'boolean',
        ]);

        $action->store($community, $request->user(), $data, $request->file('cover_image'));

        return back()->with('success', 'Event created.');
    }

    public function update(Request $request, Community $community, Event $event, ManageEvent $action): RedirectResponse
    {
        $this->authorizeOwner($request, $community);
        abort_if($event->community_id !== $community->id, 404);

        $data = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string|max:5000',
            'start_at'        => 'required|date',
            'end_at'          => 'nullable|date|after:start_at',
            'timezone'        => 'required|string|timezone',
            'url'             => 'nullable|url|max:500',
            'cover_image'     => 'nullable|image|max:10240',
            'is_members_only' => 'boolean',
        ]);

        $action->update($event, $data, $request->file('cover_image'));

        return back()->with('success', 'Event updated.');
    }

    public function destroy(Request $request, Community $community, Event $event, ManageEvent $action): RedirectResponse
    {
        $this->authorizeOwner($request, $community);
        abort_if($event->community_id !== $community->id, 404);

        $action->destroy($event);

        return back()->with('success', 'Event deleted.');
    }

    private function authorizeOwner(Request $request, Community $community): void
    {
        abort_unless($request->user()?->id === $community->owner_id, 403);
    }
}
