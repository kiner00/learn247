<?php

namespace App\Queries\DirectMessage;

use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Support\Collection;

class SearchMessageableUsers
{
    public function execute(int $userId, string $search = '', int $limit = 10): Collection
    {
        $communityIds = CommunityMember::where('user_id', $userId)->pluck('community_id');

        return User::select('id', 'name', 'username', 'avatar')
            ->whereHas('communityMemberships', fn ($q) => $q->whereIn('community_id', $communityIds))
            ->where('id', '!=', $userId)
            ->when($search, fn ($q) => $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            }))
            ->limit($limit)
            ->get();
    }
}
