<?php

namespace App\Actions\Community;

use App\Events\MemberJoined;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Notification;
use App\Models\User;
use App\Support\CacheKeys;
use Illuminate\Validation\ValidationException;

class JoinCommunity
{
    private const MILESTONES = [100, 500, 1_000, 10_000, 100_000, 1_000_000];

    private const MILESTONE_LABELS = [
        100     => '100 🥉',
        500     => '500 🥈',
        1_000   => '1k 🥇',
        10_000  => '10k 💎',
        100_000 => '100k 🏆',
        1_000_000 => '1M 🌟',
    ];

    /** @throws ValidationException */
    public function execute(User $user, Community $community): CommunityMember
    {
        if (CommunityMember::where('community_id', $community->id)->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'community' => 'You are already a member of this community.',
            ]);
        }

        if (! $community->isFree()) {
            throw ValidationException::withMessages([
                'community' => 'This is a paid community. Please subscribe to join.',
            ]);
        }

        $beforeCount = $community->members()->count();

        $member = CommunityMember::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'role'         => CommunityMember::ROLE_MEMBER,
            'joined_at'    => now(),
        ]);

        CacheKeys::flushUserMembership($user->id);

        $this->notifyOwner($user, $community);
        $this->checkMilestones($community, $beforeCount, $beforeCount + 1);

        MemberJoined::dispatch($member);

        return $member;
    }

    private function notifyOwner(User $user, Community $community): void
    {
        if ($community->owner_id === $user->id) {
            return;
        }

        Notification::create([
            'user_id'      => $community->owner_id,
            'actor_id'     => $user->id,
            'community_id' => $community->id,
            'type'         => 'new_member',
            'data'         => ['message' => "{$user->name} joined {$community->name}"],
        ]);
    }

    private function checkMilestones(Community $community, int $beforeCount, int $afterCount): void
    {
        foreach (self::MILESTONES as $milestone) {
            if ($beforeCount < $milestone && $afterCount >= $milestone) {
                Notification::create([
                    'user_id'      => $community->owner_id,
                    'actor_id'     => null,
                    'community_id' => $community->id,
                    'type'         => 'milestone',
                    'data'         => [
                        'milestone' => $milestone,
                        'message'   => "🎉 {$community->name} just hit " . self::MILESTONE_LABELS[$milestone] . " members!",
                    ],
                ]);
                break;
            }
        }
    }
}
