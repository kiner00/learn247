<?php

namespace App\Queries\DirectMessage;

use App\Models\DirectMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetConversationThread
{
    /**
     * @return array{partner: array, messages: Collection}
     */
    public function execute(int $myId, int $partnerId, int $limit = 100): array
    {
        $messages = DirectMessage::where(function ($q) use ($myId, $partnerId) {
            $q->where('sender_id', $myId)->where('receiver_id', $partnerId);
        })->orWhere(function ($q) use ($myId, $partnerId) {
            $q->where('sender_id', $partnerId)->where('receiver_id', $myId);
        })->oldest()->take($limit)->get()->map(fn ($m) => [
            'id'         => $m->id,
            'content'    => $m->content,
            'is_mine'    => $m->sender_id === $myId,
            'created_at' => $m->created_at,
        ]);

        DirectMessage::where('sender_id', $partnerId)
            ->where('receiver_id', $myId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ['messages' => $messages];
    }

    /**
     * Return new messages from a partner after a given ID, and mark them as read.
     */
    public function poll(int $myId, int $partnerId, int $afterId, int $limit = 50): Collection
    {
        $messages = DirectMessage::where('sender_id', $partnerId)
            ->where('receiver_id', $myId)
            ->where('id', '>', $afterId)
            ->oldest()
            ->take($limit)
            ->get()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'content'    => $m->content,
                'is_mine'    => false,
                'created_at' => $m->created_at,
            ]);

        if ($messages->isNotEmpty()) {
            DirectMessage::where('sender_id', $partnerId)
                ->where('receiver_id', $myId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return $messages;
    }
}
