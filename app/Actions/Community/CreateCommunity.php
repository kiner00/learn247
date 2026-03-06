<?php

namespace App\Actions\Community;

use App\Models\Affiliate;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Support\Str;

class CreateCommunity
{
    public function execute(User $user, array $data): Community
    {
        $community = Community::create([
            'name'        => $data['name'],
            'slug'        => $data['slug'] ?? Str::slug($data['name']),
            'owner_id'    => $user->id,
            'description' => $data['description'] ?? null,
            'avatar'      => $data['avatar'] ?? null,
            'is_private'  => $data['is_private'] ?? false,
            'price'       => $data['price'] ?? 0,
            'currency'    => $data['currency'] ?? 'PHP',
        ]);

        // Owner is automatically an admin member
        CommunityMember::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'role'         => CommunityMember::ROLE_ADMIN,
            'joined_at'    => now(),
        ]);

        // Owner gets an affiliate/invite code automatically
        do {
            $code = Str::random(12);
        } while (Affiliate::where('code', $code)->exists());

        Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'code'         => $code,
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        return $community;
    }
}
