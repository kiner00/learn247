<?php

namespace App\Queries\Community;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class GetCalendarEvents
{
    public function execute(Community $community, ?int $userId, int $year, int $month): array
    {
        $isMember = $userId && CommunityMember::where('community_id', $community->id)
            ->where('user_id', $userId)
            ->exists();
        $isOwner = $userId && $userId === $community->owner_id;

        $from = now()->setDate($year, $month, 1)->startOfDay();
        $to   = $from->copy()->endOfMonth()->endOfDay();

        $query = $community->events()->whereBetween('start_at', [$from, $to]);

        if (! $isMember && ! $isOwner) {
            $query->where('is_members_only', false);
        }

        $events = $query->get()->map(fn (Event $e) => [
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

        return [
            'events'     => $events,
            'membership' => $isMember || $isOwner ? ['role' => $isOwner ? 'owner' : 'member'] : null,
            'isOwner'    => (bool) $isOwner,
        ];
    }
}
