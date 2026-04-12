<?php

namespace App\Services\Community;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CurzzoPurchase;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Collection;

class CurzzoAccessService
{
    /**
     * Build the access context for a user viewing a community's curzzos.
     *
     * @param  Collection<int>  $curzzoIds  ids already loaded, used to batch-check paid purchases (avoids N+1)
     * @return array{user_id: ?int, is_owner: bool, is_member: bool, is_paid_member: bool, was_ever_member: bool, paid_curzzo_ids: Collection<int>}
     */
    public function buildContext(?User $user, Community $community, Collection $curzzoIds): array
    {
        $userId  = $user?->id;
        $isOwner = $userId !== null && $userId === $community->owner_id;

        $isMember = $userId !== null && CommunityMember::query()
            ->where('community_id', $community->id)
            ->where('user_id', $userId)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();

        $isPaidMember = $userId !== null && Subscription::query()
            ->where('community_id', $community->id)
            ->where('user_id', $userId)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();

        $wasEverMember = $userId !== null && Subscription::query()
            ->where('community_id', $community->id)
            ->where('user_id', $userId)
            ->whereIn('status', [
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_EXPIRED,
                Subscription::STATUS_CANCELLED,
            ])
            ->exists();

        $paidCurzzoIds = collect();
        if ($userId !== null && ! $isOwner && $curzzoIds->isNotEmpty()) {
            $paidCurzzoIds = CurzzoPurchase::query()
                ->where('user_id', $userId)
                ->whereIn('curzzo_id', $curzzoIds)
                ->where('status', CurzzoPurchase::STATUS_PAID)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->pluck('curzzo_id');
        }

        return [
            'user_id'         => $userId,
            'is_owner'        => $isOwner,
            'is_member'       => $isMember,
            'is_paid_member'  => $isPaidMember,
            'was_ever_member' => $wasEverMember,
            'paid_curzzo_ids' => $paidCurzzoIds,
        ];
    }

    public function hasAccess($curzzo, array $context): bool
    {
        if ($context['is_owner']) {
            return true;
        }

        return match ($curzzo->access_type ?? 'free') {
            'free'                       => $context['is_member'],
            'inclusive'                  => $context['is_paid_member'],
            'paid_once', 'paid_monthly'  => $context['user_id'] !== null
                && $context['paid_curzzo_ids']->contains($curzzo->id),
            'member_once'                => $context['was_ever_member'],
            default                      => false,
        };
    }
}
