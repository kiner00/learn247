<?php

namespace App\Actions\Community;

use App\Models\CommunityInvite;
use App\Models\CommunityMember;
use App\Models\Subscription;
use App\Models\User;

class AcceptInvite
{
    /**
     * @return array{success: bool, message: string, redirect: string}
     */
    public function execute(User $user, CommunityInvite $invite): array
    {
        $community = $invite->community;

        if ($invite->isExpired()) {
            return [
                'success'  => false,
                'message'  => 'This invite link has expired.',
                'redirect' => 'about',
            ];
        }

        if ($invite->isAccepted()) {
            return [
                'success'  => true,
                'message'  => 'You already have access to this community.',
                'redirect' => 'show',
            ];
        }

        if (strtolower($user->email) !== strtolower($invite->email)) {
            return [
                'success'  => false,
                'message'  => "This invite was sent to {$invite->email}. Please log in with that email address to accept it.",
                'redirect' => 'about',
            ];
        }

        CommunityMember::firstOrCreate(
            ['community_id' => $community->id, 'user_id' => $user->id],
            ['role' => CommunityMember::ROLE_MEMBER, 'joined_at' => now()]
        );

        if (! $community->isFree()) {
            Subscription::firstOrCreate(
                ['community_id' => $community->id, 'user_id' => $user->id],
                [
                    'status'     => Subscription::STATUS_ACTIVE,
                    'expires_at' => null,
                ]
            );
        }

        $invite->update(['accepted_at' => now()]);

        return [
            'success'  => true,
            'message'  => "Welcome to {$community->name}!",
            'redirect' => 'show',
        ];
    }
}
