<?php

namespace App\Queries\Chat;

use App\Models\Community;
use App\Models\Message;
use Illuminate\Support\Collection;

class GetChatMessages
{
    public function latest(Community $community, int $limit = 50): Collection
    {
        return Message::where('community_id', $community->id)
            ->with('user:id,name,username,avatar')
            ->latest()
            ->take($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function after(Community $community, int $afterId, int $limit = 50): Collection
    {
        return Message::where('community_id', $community->id)
            ->where('id', '>', $afterId)
            ->with('user:id,name,username,avatar')
            ->oldest()
            ->take($limit)
            ->get();
    }

    public function markAsRead(Community $community, int $userId): void
    {
        $community->members()->where('user_id', $userId)->update([
            'messages_last_read_at' => now(),
        ]);
    }
}
