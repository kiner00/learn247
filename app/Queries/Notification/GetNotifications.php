<?php

namespace App\Queries\Notification;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class GetNotifications
{
    public function paginated(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Notification::where('user_id', $user->id)
            ->with(['actor:id,name,avatar', 'community:id,name,slug'])
            ->latest()
            ->paginate($perPage);
    }

    public function recent(User $user, int $limit = 20): Collection
    {
        return Notification::where('user_id', $user->id)
            ->with(['actor:id,name,avatar', 'community:id,name,slug'])
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn ($n) => [
                'id'             => $n->id,
                'type'           => $n->type,
                'data'           => $n->data,
                'read_at'        => $n->read_at,
                'created_at'     => $n->created_at,
                'actor_name'     => $n->actor?->name,
                'actor_avatar'   => $n->actor?->avatar,
                'community_name' => $n->community?->name,
                'community_slug' => $n->community?->slug,
            ]);
    }
}
