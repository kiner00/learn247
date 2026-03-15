<?php

namespace App\Queries\Profile;

use App\Models\Badge;
use App\Models\Comment;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetProfileData
{
    /**
     * @return array{
     *   user: User,
     *   is_own: bool,
     *   memberships: Collection,
     *   total_points: int,
     *   level: int,
     *   points_to_next: int|null,
     *   activity_map: array,
     *   badges: Collection
     * }
     */
    public function execute(User $user, bool $isOwn): array
    {
        $levelData = $this->getLevelData($user);

        return [
            'user'           => $user,
            'is_own'         => $isOwn,
            'memberships'    => $this->getMemberships($user),
            'total_points'   => $levelData['total_points'],
            'level'          => $levelData['level'],
            'points_to_next' => $levelData['points_to_next'],
            'activity_map'   => $this->getActivityMap($user),
            'badges'         => $this->getBadges($user, $isOwn),
        ];
    }

    public function getMemberships(User $user): Collection
    {
        return CommunityMember::where('user_id', $user->id)
            ->with('community:id,name,slug,avatar,price')
            ->get();
    }

    /**
     * @return array{total_points: int, level: int, points_to_next: int|null}
     */
    public function getLevelData(User $user): array
    {
        $totalPoints = CommunityMember::where('user_id', $user->id)->sum('points');
        $level       = CommunityMember::computeLevel((int) $totalPoints);
        $thresholds  = CommunityMember::LEVEL_THRESHOLDS;
        $nextThresh  = $thresholds[$level] ?? null;

        return [
            'total_points'   => (int) $totalPoints,
            'level'          => $level,
            'points_to_next' => $nextThresh !== null ? $nextThresh - $totalPoints : null,
        ];
    }

    public function getActivityMap(User $user): array
    {
        $since = Carbon::now()->subWeeks(52)->startOfDay();

        $postDates = Post::where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->whereNull('deleted_at')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as cnt')
            ->groupBy('date')
            ->pluck('cnt', 'date');

        $commentDates = Comment::where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->whereNull('deleted_at')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as cnt')
            ->groupBy('date')
            ->pluck('cnt', 'date');

        $activityMap = [];
        $cursor      = $since->copy();
        while ($cursor <= now()) {
            $d     = $cursor->toDateString();
            $count = (int) ($postDates[$d] ?? 0) + (int) ($commentDates[$d] ?? 0);
            if ($count > 0) {
                $activityMap[$d] = $count;
            }
            $cursor->addDay();
        }

        return $activityMap;
    }

    public function getBadges(User $user, bool $isOwn): Collection
    {
        $earnedBadgeIds = UserBadge::where('user_id', $user->id)
            ->pluck('earned_at', 'badge_id');

        if ($isOwn) {
            return Badge::whereNull('community_id')
                ->whereNotNull('key')
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($b) => [
                    'key'         => $b->key,
                    'name'        => $b->name,
                    'icon'        => $b->icon,
                    'description' => $b->description,
                    'how_to_earn' => $b->how_to_earn,
                    'type'        => $b->type,
                    'earned'      => $earnedBadgeIds->has($b->id),
                    'earned_at'   => $earnedBadgeIds->get($b->id)?->toDateString(),
                ])->values();
        }

        return Badge::whereNull('community_id')
            ->whereNotNull('key')
            ->whereIn('id', $earnedBadgeIds->keys())
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($b) => [
                'key'         => $b->key,
                'name'        => $b->name,
                'icon'        => $b->icon,
                'description' => $b->description,
                'how_to_earn' => $b->how_to_earn,
                'type'        => $b->type,
                'earned'      => true,
                'earned_at'   => $earnedBadgeIds->get($b->id)?->toDateString(),
            ])->values();
    }

    public function getContributionsCount(User $user, ?int $communityId): int
    {
        if (! $communityId) {
            return 0;
        }

        return Post::where('user_id', $user->id)->where('community_id', $communityId)->whereNull('deleted_at')->count()
            + Comment::where('user_id', $user->id)->where('community_id', $communityId)->whereNull('deleted_at')->count();
    }
}
