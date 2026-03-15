<?php

namespace App\Queries\Badge;

use App\Models\Badge;
use App\Models\UserBadge;
use Illuminate\Support\Collection;

class GetBadges
{
    /**
     * @return array{member: Collection, creator: Collection}
     */
    public function execute(?int $userId = null): array
    {
        $allBadges = Badge::whereNull('community_id')
            ->whereNotNull('key')
            ->orderBy('sort_order')
            ->get();

        $earnedBadgeIds = $userId
            ? UserBadge::where('user_id', $userId)
                ->whereIn('badge_id', $allBadges->pluck('id'))
                ->pluck('badge_id')
                ->flip()
            : collect();

        $format = fn (Badge $badge) => [
            'id'          => $badge->id,
            'key'         => $badge->key,
            'name'        => $badge->name,
            'icon'        => $badge->icon,
            'description' => $badge->description,
            'how_to_earn' => $badge->how_to_earn,
            'type'        => $badge->type,
            'earned'      => $earnedBadgeIds->has($badge->id),
        ];

        return [
            'member'  => $allBadges->where('type', 'member')->map($format)->values(),
            'creator' => $allBadges->where('type', 'creator')->map($format)->values(),
        ];
    }
}
