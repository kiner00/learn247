<?php

namespace App\Services\Community;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CurzzoMessage;
use App\Models\CurzzoPurchase;
use App\Models\CurzzoTopup;
use App\Models\User;

class CurzzoLimitService
{
    private const LIMIT_FREE    = 10;
    private const LIMIT_PAID    = 50;
    private const LIMIT_BUYER   = 100;
    private const COMMUNITY_MONTHLY_LIMIT = 10000;

    public const DEFAULT_PACKS = [
        ['messages' => 50,  'price' => 49,  'label' => '50 Messages'],
        ['messages' => 200, 'price' => 149, 'label' => '200 Messages'],
        ['messages' => 0,   'price' => 99,  'label' => 'Unlimited Day Pass'],
    ];

    /**
     * Daily message limit for a user in a community.
     */
    public function dailyLimit(User $user, Community $community): int
    {
        if ($user->id === $community->owner_id) {
            return PHP_INT_MAX;
        }

        // Check if user has purchased any paid Curzzo in this community
        $hasCurzzoPurchase = CurzzoPurchase::where('user_id', $user->id)
            ->whereIn('curzzo_id', $community->curzzos()->pluck('id'))
            ->where('status', CurzzoPurchase::STATUS_PAID)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();

        if ($hasCurzzoPurchase) {
            return self::LIMIT_BUYER;
        }

        // Check membership type
        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->first();

        if ($member && $member->membership_type === CommunityMember::MEMBERSHIP_PAID) {
            return self::LIMIT_PAID;
        }

        return self::LIMIT_FREE;
    }

    /**
     * Count user messages sent today in this community (across all Curzzos).
     */
    public function todayUsage(User $user, Community $community): int
    {
        return CurzzoMessage::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->where('role', 'user')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    /**
     * Count all member messages this month in the community.
     */
    public function communityMonthlyUsage(Community $community): int
    {
        return CurzzoMessage::where('community_id', $community->id)
            ->where('role', 'user')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    /**
     * Remaining top-up credits for a user in a community.
     */
    public function topupRemaining(User $user, Community $community): int
    {
        $topups = CurzzoTopup::where('user_id', $user->id)
            ->where('community_id', $community->id)
            ->where('status', CurzzoTopup::STATUS_PAID)
            ->get();

        $remaining = 0;
        foreach ($topups as $topup) {
            if ($topup->isActive()) {
                if ($topup->messages === 0) {
                    return PHP_INT_MAX; // active day pass
                }
                $remaining += $topup->remainingMessages();
            }
        }

        return $remaining;
    }

    /**
     * Check if user can send a message. Returns status array.
     */
    public function canSendMessage(User $user, Community $community): array
    {
        $dailyLimit = $this->dailyLimit($user, $community);
        $dailyUsed  = $this->todayUsage($user, $community);
        $topupRemaining = $this->topupRemaining($user, $community);

        $base = [
            'daily_limit'      => $dailyLimit >= PHP_INT_MAX ? -1 : $dailyLimit,
            'daily_used'       => $dailyUsed,
            'topup_remaining'  => $topupRemaining >= PHP_INT_MAX ? -1 : $topupRemaining,
        ];

        // Owner bypass
        if ($user->id === $community->owner_id) {
            return array_merge($base, ['allowed' => true, 'reason' => null]);
        }

        // Community monthly cap
        if ($this->communityMonthlyUsage($community) >= self::COMMUNITY_MONTHLY_LIMIT) {
            return array_merge($base, [
                'allowed' => false,
                'reason'  => 'This community has reached its monthly AI message limit. Please try again next month.',
            ]);
        }

        // Under daily limit
        if ($dailyUsed < $dailyLimit) {
            return array_merge($base, ['allowed' => true, 'reason' => null]);
        }

        // Over daily limit but has topup
        if ($topupRemaining > 0) {
            return array_merge($base, ['allowed' => true, 'reason' => null, 'using_topup' => true]);
        }

        // Over daily limit, no topup
        return array_merge($base, [
            'allowed' => false,
            'reason'  => "You've reached your daily limit of {$dailyLimit} messages. Top up to keep chatting!",
        ]);
    }

    /**
     * Consume one message from the oldest active top-up.
     */
    public function consumeTopup(User $user, Community $community): void
    {
        $topup = CurzzoTopup::where('user_id', $user->id)
            ->where('community_id', $community->id)
            ->where('status', CurzzoTopup::STATUS_PAID)
            ->where(function ($q) {
                // Message packs with remaining credits
                $q->where(function ($q2) {
                    $q2->where('messages', '>', 0)
                       ->whereColumn('messages_used', '<', 'messages');
                })
                // Or active day passes
                ->orWhere(function ($q2) {
                    $q2->where('messages', 0)
                       ->where('expires_at', '>', now());
                });
            })
            ->orderBy('created_at')
            ->first();

        if ($topup && $topup->messages > 0) {
            $topup->increment('messages_used');
        }
        // Day passes don't need decrementing
    }

    /**
     * Get top-up packs for a community (custom or default).
     */
    public function getPacks(Community $community): array
    {
        return $community->curzzo_topup_packs ?? self::DEFAULT_PACKS;
    }
}
