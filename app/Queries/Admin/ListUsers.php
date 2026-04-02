<?php

namespace App\Queries\Admin;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ListUsers
{
    public function execute(string $search): array
    {
        $users = User::withCount('communityMemberships')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($u) => [
                'id'             => $u->id,
                'name'           => $u->name,
                'email'          => $u->email,
                'username'       => $u->username,
                'is_active'      => (bool) $u->is_active,
                'is_super_admin' => (bool) $u->is_super_admin,
                'kyc_verified'   => $u->kyc_verified_at !== null,
                'memberships'    => $u->community_memberships_count,
                'created_at'     => $u->created_at?->toDateString(),
            ]);

        return [
            'users'   => $users,
            'filters' => ['search' => $search],
        ];
    }
}
