<?php

namespace App\Actions\Community;

use App\Events\MemberJoined;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Notification;
use App\Models\User;
use App\Support\CacheKeys;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class JoinCommunity
{
    private const MILESTONES = [100, 500, 1_000, 10_000, 100_000, 1_000_000];

    private const MILESTONE_LABELS = [
        100 => '100 🥉',
        500 => '500 🥈',
        1_000 => '1k 🥇',
        10_000 => '10k 💎',
        100_000 => '100k 🏆',
        1_000_000 => '1M 🌟',
    ];

    /** @throws ValidationException */
    public function execute(User $user, Community $community): CommunityMember
    {
        if (! $community->isFree()) {
            throw ValidationException::withMessages([
                'community' => 'This is a paid community. Please subscribe to join.',
            ]);
        }

        return $this->createMember($user, $community);
    }

    /**
     * Trial join — grants temporary free membership on a paid community.
     *
     * @throws ValidationException
     */
    public function executeAsTrial(User $user, Community $community, Carbon $expiresAt): CommunityMember
    {
        return $this->createMember($user, $community, [
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides  Extra attributes (membership_type, expires_at).
     *
     * @throws ValidationException
     */
    private function createMember(User $user, Community $community, array $overrides = []): CommunityMember
    {
        if (CommunityMember::where('community_id', $community->id)->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'community' => 'You are already a member of this community.',
            ]);
        }

        $beforeCount = $community->members()->count();

        $member = CommunityMember::create(array_merge([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => CommunityMember::ROLE_MEMBER,
            'joined_at' => now(),
        ], $overrides));

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
            'user_id' => $community->owner_id,
            'actor_id' => $user->id,
            'community_id' => $community->id,
            'type' => 'new_member',
            'data' => ['message' => "{$user->name} joined {$community->name}"],
        ]);
    }

    private function checkMilestones(Community $community, int $beforeCount, int $afterCount): void
    {
        foreach (self::MILESTONES as $milestone) {
            if ($beforeCount < $milestone && $afterCount >= $milestone) {
                Notification::create([
                    'user_id' => $community->owner_id,
                    'actor_id' => null,
                    'community_id' => $community->id,
                    'type' => 'milestone',
                    'data' => [
                        'milestone' => $milestone,
                        'message' => "🎉 {$community->name} just hit ".self::MILESTONE_LABELS[$milestone].' members!',
                    ],
                ]);
                break;
            }
        }
    }
}
