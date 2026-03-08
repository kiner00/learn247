<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\UserBadge;
use Inertia\Inertia;
use Inertia\Response;

class BadgeController extends Controller
{
    public function index(): Response
    {
        $userId = auth()->id();

        // All platform badges (no community_id), ordered
        $allBadges = Badge::whereNull('community_id')
            ->whereNotNull('key')
            ->orderBy('sort_order')
            ->get();

        // Earned badge IDs for this user
        $earnedBadgeIds = $userId
            ? UserBadge::where('user_id', $userId)
                ->whereIn('badge_id', $allBadges->pluck('id'))
                ->pluck('badge_id')
                ->flip()
            : collect();

        $memberBadges = $allBadges
            ->where('type', 'member')
            ->map(fn ($b) => $this->format($b, $earnedBadgeIds))
            ->values();

        $creatorBadges = $allBadges
            ->where('type', 'creator')
            ->map(fn ($b) => $this->format($b, $earnedBadgeIds))
            ->values();

        return Inertia::render('Badges/Index', compact('memberBadges', 'creatorBadges'));
    }

    private function format(Badge $badge, $earnedIds): array
    {
        $earned = $earnedIds->has($badge->id);

        return [
            'id'          => $badge->id,
            'key'         => $badge->key,
            'name'        => $badge->name,
            'icon'        => $badge->icon,
            'description' => $badge->description,
            'how_to_earn' => $badge->how_to_earn,
            'type'        => $badge->type,
            'earned'      => $earned,
        ];
    }
}
