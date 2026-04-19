<?php

namespace App\Queries\Community;

use App\Models\Community;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListCommunities
{
    public function execute(string $search = '', string $category = '', string $sort = 'latest', int $perPage = 15): LengthAwarePaginator
    {
        $sort = in_array($sort, ['popular', 'latest']) ? $sort : 'latest';

        return Community::with('owner')
            ->withCount('members')
            ->where('is_private', false)
            ->whereHas('owner', fn ($q) => $q->where('kyc_status', User::KYC_APPROVED)->orWhereNotNull('kyc_verified_at'))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($category && $category !== 'All', fn ($q) => $q->where('category', $category))
            ->when($sort === 'popular', fn ($q) => $q->orderByDesc('members_count'))
            ->when($sort === 'latest', fn ($q) => $q->latest())
            ->paginate($perPage)
            ->withQueryString();
    }
}
