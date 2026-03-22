<?php

namespace App\Queries\Community;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\Subscription;
use Illuminate\Support\Facades\Storage;

class GetCalendarEvents
{
    public function execute(Community $community, ?int $userId, int $year, int $month): array
    {
        $isOwner = $userId && $userId === $community->owner_id;

        $memberRecord = $userId
            ? CommunityMember::where('community_id', $community->id)
                ->where('user_id', $userId)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->first(['membership_type'])
            : null;

        $isPaidMember = $memberRecord && $memberRecord->membership_type === CommunityMember::MEMBERSHIP_PAID
            || ($userId && Subscription::where('community_id', $community->id)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists());

        $isFreeMember = $memberRecord !== null; // free or paid

        $from = now()->setDate($year, $month, 1)->startOfDay();
        $to   = $from->copy()->endOfMonth()->endOfDay();

        $query = $community->events()->whereBetween('start_at', [$from, $to]);

        if ($isOwner) {
            // Owner sees everything
        } elseif ($isPaidMember) {
            // Paid member sees public + free + paid
        } elseif ($isFreeMember) {
            // Free member sees public + free only
            $query->whereIn('visibility', [Event::VISIBILITY_PUBLIC, Event::VISIBILITY_FREE]);
        } else {
            // Guest / non-member sees only public
            $query->where('visibility', Event::VISIBILITY_PUBLIC);
        }

        $events = $query->get()->map(fn (Event $e) => [
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

        return [
            'events'     => $events,
            'membership' => ($isFreeMember || $isOwner) ? ['role' => $isOwner ? 'owner' : 'member'] : null,
            'isOwner'    => (bool) $isOwner,
        ];
    }
}
