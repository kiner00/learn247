<?php

namespace App\Http\Controllers\Api;

use App\Actions\Community\ManageEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    public function index(Request $request, Community $community): JsonResponse
    {
        $user     = $request->user();
        $isMember = $user && CommunityMember::where('community_id', $community->id)->where('user_id', $user->id)->exists();
        $isOwner  = $user && $user->id === $community->owner_id;

        $year  = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $from  = now()->setDate($year, $month, 1)->startOfDay();
        $to    = $from->copy()->endOfMonth()->endOfDay();

        $eventsQuery = $community->events()->whereBetween('start_at', [$from, $to]);
        if (! $isMember && ! $isOwner) {
            $eventsQuery->where('visibility', Event::VISIBILITY_PUBLIC);
        }

        $events = $eventsQuery->get()->map(fn (Event $e) => [
            'id'          => $e->id,
            'title'       => $e->title,
            'description' => $e->description,
            'start_at'    => $e->start_at->toISOString(),
            'end_at'      => $e->end_at?->toISOString(),
            'timezone'    => $e->timezone,
            'url'         => $e->url,
            'cover_image' => $e->cover_image ? Storage::url($e->cover_image) : null,
            'visibility'  => $e->visibility,
        ]);

        return response()->json(['events' => $events, 'year' => $year, 'month' => $month]);
    }

    public function store(StoreEventRequest $request, Community $community, ManageEvent $action): JsonResponse
    {
        abort_unless($request->user()?->id === $community->owner_id, 403);

        try {
            $event = $action->store($community, $request->user(), $request->validated(), $request->file('cover_image'));

            return response()->json(['message' => 'Event created.', 'event_id' => $event->id], 201);
        } catch (\Throwable $e) {
            Log::error('Api\EventController@store failed', ['error' => $e->getMessage(), 'community_id' => $community->id]);
            return response()->json(['message' => 'Failed to create event.'], 500);
        }
    }

    public function update(UpdateEventRequest $request, Community $community, Event $event, ManageEvent $action): JsonResponse
    {
        abort_unless($request->user()?->id === $community->owner_id, 403);
        abort_if($event->community_id !== $community->id, 404);

        try {
            $action->update($event, $request->validated(), $request->file('cover_image'));

            return response()->json(['message' => 'Event updated.']);
        } catch (\Throwable $e) {
            Log::error('Api\EventController@update failed', ['error' => $e->getMessage(), 'event_id' => $event->id]);
            return response()->json(['message' => 'Failed to update event.'], 500);
        }
    }

    public function destroy(Request $request, Community $community, Event $event, ManageEvent $action): JsonResponse
    {
        abort_unless($request->user()?->id === $community->owner_id, 403);
        abort_if($event->community_id !== $community->id, 404);

        try {
            $action->destroy($event);

            return response()->json(['message' => 'Event deleted.']);
        } catch (\Throwable $e) {
            Log::error('Api\EventController@destroy failed', ['error' => $e->getMessage(), 'event_id' => $event->id]);
            return response()->json(['message' => 'Failed to delete event.'], 500);
        }
    }
}
