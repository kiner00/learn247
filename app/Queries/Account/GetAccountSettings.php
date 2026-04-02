<?php

namespace App\Queries\Account;

use App\Models\CommunityMember;
use App\Models\User;

class GetAccountSettings
{
    private const DEFAULT_NOTIF_PREFS = [
        'follower'  => true,
        'likes'     => true,
        'kaching'   => true,
        'affiliate' => true,
    ];

    private const DEFAULT_CHAT_PREFS = [
        'notifications'      => true,
        'email_notifications' => true,
    ];

    private const DEFAULT_COMMUNITY_NOTIF_PREFS = [
        'new_posts' => true,
        'comments'  => true,
        'mentions'  => true,
    ];

    public function execute(User $user, ?string $tab = 'communities'): array
    {
        $memberships = CommunityMember::where('user_id', $user->id)
            ->with('community:id,name,slug,avatar,price,owner_id')
            ->orderBy('joined_at')
            ->get()
            ->map(fn ($m) => [
                'community_id'    => $m->community_id,
                'name'            => $m->community?->name,
                'slug'            => $m->community?->slug,
                'avatar'          => $m->community?->avatar,
                'price'           => $m->community?->price,
                'is_owner'        => $m->community?->owner_id === $user->id,
                'role'            => $m->role,
                'joined_at'       => $m->joined_at,
                'notif_prefs'     => array_merge(self::DEFAULT_COMMUNITY_NOTIF_PREFS, $m->notif_prefs ?? []),
                'chat_enabled'    => $m->chat_enabled ?? true,
                'show_on_profile' => $m->show_on_profile ?? true,
            ]);

        return [
            'tab'           => $tab,
            'profileUser'   => [
                'first_name'       => explode(' ', $user->name, 2)[0] ?? '',
                'last_name'        => explode(' ', $user->name, 2)[1] ?? '',
                'username'         => $user->username,
                'bio'              => $user->bio,
                'email'            => $user->email,
                'avatar'           => $user->avatar,
                'location'         => $user->location,
                'social_links'     => $user->social_links ?? [],
                'hide_from_search' => $user->hide_from_search ?? false,
            ],
            'memberships'   => $memberships->values(),
            'affiliateLink' => url('/register?ref=' . $user->username),
            'timezone'      => $user->timezone ?? 'Asia/Manila',
            'theme'         => $user->theme ?? 'light',
            'notifPrefs'    => array_merge(self::DEFAULT_NOTIF_PREFS, $user->notification_prefs ?? []),
            'chatPrefs'     => array_merge(self::DEFAULT_CHAT_PREFS, $user->chat_prefs ?? []),
            'payoutMethod'  => $user->payout_method,
            'payoutDetails' => $user->payout_details,
            'bankName'      => $user->bank_name,
            'cryptoWallet'  => $user->crypto_wallet,
            'crzBalance'    => (float) $user->crz_token_balance,
            'kyc'           => [
                'status'          => $user->kyc_status ?? 'none',
                'id_document'     => $user->kyc_id_document,
                'selfie'          => $user->kyc_selfie,
                'submitted_at'    => $user->kyc_submitted_at?->toDateTimeString(),
                'rejected_reason' => $user->kyc_rejected_reason,
            ],
        ];
    }
}
