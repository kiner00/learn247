<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\ManageEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Community;
use App\Models\Event;
use App\Queries\Community\GetCalendarEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request, Community $community, GetCalendarEvents $query): Response
    {
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $data = $query->execute($community, auth()->id(), $year, $month);

        return Inertia::render('Communities/Calendar', [
            'community' => $community->only('id', 'name', 'slug', 'avatar', 'cover_image'),
            'membership' => $data['membership'],
            'events' => $data['events'],
            'year' => $year,
            'month' => $month,
            'isOwner' => $data['isOwner'],
            'userTimezone' => $request->user()?->timezone ?? 'UTC',
        ]);
    }

    public function store(StoreEventRequest $request, Community $community, ManageEvent $action): RedirectResponse
    {
        $this->authorizeOwner($request, $community);

        try {
            $action->store($community, $request->user(), $request->validated(), $request->file('cover_image'));

            return back()->with('success', 'Event created.');
        } catch (\Throwable $e) {
            Log::error('EventController@store failed', ['error' => $e->getMessage(), 'community_id' => $community->id]);

            return back()->with('error', 'Failed to create event.');
        }
    }

    public function update(UpdateEventRequest $request, Community $community, Event $event, ManageEvent $action): RedirectResponse
    {
        $this->authorizeOwner($request, $community);
        abort_if($event->community_id !== $community->id, 404);

        try {
            $action->update($event, $request->validated(), $request->file('cover_image'));

            return back()->with('success', 'Event updated.');
        } catch (\Throwable $e) {
            Log::error('EventController@update failed', ['error' => $e->getMessage(), 'event_id' => $event->id]);

            return back()->with('error', 'Failed to update event.');
        }
    }

    public function destroy(Request $request, Community $community, Event $event, ManageEvent $action): RedirectResponse
    {
        $this->authorizeOwner($request, $community);
        abort_if($event->community_id !== $community->id, 404);

        try {
            $action->destroy($event);

            return back()->with('success', 'Event deleted.');
        } catch (\Throwable $e) {
            Log::error('EventController@destroy failed', ['error' => $e->getMessage(), 'event_id' => $event->id]);

            return back()->with('error', 'Failed to delete event.');
        }
    }

    private function authorizeOwner(Request $request, Community $community): void
    {
        abort_unless($request->user()?->id === $community->owner_id, 403);
    }
}
