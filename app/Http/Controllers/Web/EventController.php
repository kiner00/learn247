<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\ManageEvent;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Event;
use App\Queries\Community\GetCalendarEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request, Community $community, GetCalendarEvents $query): Response
    {
        $year  = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $data = $query->execute($community, auth()->id(), $year, $month);

        return Inertia::render('Communities/Calendar', [
            'community'    => $community->only('id', 'name', 'slug', 'avatar', 'cover_image'),
            'membership'   => $data['membership'],
            'events'       => $data['events'],
            'year'         => $year,
            'month'        => $month,
            'isOwner'      => $data['isOwner'],
            'userTimezone' => $request->user()?->timezone ?? 'UTC',
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
            'visibility'      => 'required|in:public,free,paid',
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
            'visibility'      => 'required|in:public,free,paid',
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
