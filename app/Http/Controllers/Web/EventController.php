<?php

namespace App\Http\Controllers\Web;

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
        $user       = $request->user();
        $isMember   = $user && CommunityMember::where('community_id', $community->id)
                        ->where('user_id', $user->id)->exists();
        $isOwner    = $user && $user->id === $community->owner_id;

        // Month to display (default current)
        $year  = (int) $request->get('year',  now()->year);
        $month = (int) $request->get('month', now()->month);

        $from = now()->setDate($year, $month, 1)->startOfDay();
        $to   = $from->copy()->endOfMonth()->endOfDay();

        $eventsQuery = $community->events()
            ->whereBetween('start_at', [$from, $to]);

        // Non-members see public events only
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
            'community'  => $community->only('id', 'name', 'slug', 'avatar', 'cover_image'),
            'membership' => $isMember || $isOwner
                ? ['role' => $isOwner ? 'owner' : 'member']
                : null,
            'events'     => $events,
            'year'       => $year,
            'month'      => $month,
            'isOwner'    => $isOwner,
            'userTimezone' => $user?->timezone ?? 'UTC',
        ]);
    }

    public function store(Request $request, Community $community): RedirectResponse
    {
        $this->authorizeOwner($request, $community);

        $data = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string|max:5000',
            'start_at'        => 'required|date',
            'end_at'          => 'nullable|date|after:start_at',
            'timezone'        => 'required|string|timezone',
            'url'             => 'nullable|url|max:500',
            'cover_image'     => 'nullable|image|max:4096',
            'is_members_only' => 'boolean',
        ]);

        $path = null;
        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store("events/{$community->id}", 'public');
        }

        $community->events()->create([
            'created_by'      => $request->user()->id,
            'title'           => $data['title'],
            'description'     => $data['description'] ?? null,
            'start_at'        => $data['start_at'],
            'end_at'          => $data['end_at'] ?? null,
            'timezone'        => $data['timezone'],
            'url'             => $data['url'] ?? null,
            'cover_image'     => $path,
            'is_members_only' => $data['is_members_only'] ?? false,
        ]);

        return back()->with('success', 'Event created.');
    }

    public function update(Request $request, Community $community, Event $event): RedirectResponse
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
            'cover_image'     => 'nullable|image|max:4096',
            'is_members_only' => 'boolean',
        ]);

        if ($request->hasFile('cover_image')) {
            if ($event->cover_image) {
                Storage::disk('public')->delete($event->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store("events/{$community->id}", 'public');
        }

        $event->update($data);

        return back()->with('success', 'Event updated.');
    }

    public function destroy(Request $request, Community $community, Event $event): RedirectResponse
    {
        $this->authorizeOwner($request, $community);
        abort_if($event->community_id !== $community->id, 404);

        if ($event->cover_image) {
            Storage::disk('public')->delete($event->cover_image);
        }
        $event->delete();

        return back()->with('success', 'Event deleted.');
    }

    private function authorizeOwner(Request $request, Community $community): void
    {
        abort_unless($request->user()?->id === $community->owner_id, 403);
    }
}
