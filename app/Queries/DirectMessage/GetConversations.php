<?php

namespace App\Queries\DirectMessage;

use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Support\Collection;

class GetConversations
{
    public function execute(int $userId): Collection
    {
        $partnerIds = DirectMessage::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->selectRaw('CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as partner_id', [$userId])
            ->distinct()
            ->pluck('partner_id');

        return $partnerIds->map(function ($partnerId) use ($userId) {
            $latest = DirectMessage::where(function ($q) use ($userId, $partnerId) {
                $q->where('sender_id', $userId)->where('receiver_id', $partnerId);
            })->orWhere(function ($q) use ($userId, $partnerId) {
                $q->where('sender_id', $partnerId)->where('receiver_id', $userId);
            })->latest()->first();

            $unread = DirectMessage::where('sender_id', $partnerId)
                ->where('receiver_id', $userId)
                ->whereNull('read_at')
                ->count();

            $partner = User::select('id', 'name', 'username', 'avatar')->find($partnerId);

            return [
                'user' => $partner,
                'latest_message' => $latest ? [
                    'content' => $latest->content,
                    'created_at' => $latest->created_at,
                    'is_mine' => $latest->sender_id === $userId,
                ] : null,
                'unread_count' => $unread,
            ];
        })->sortByDesc(fn ($c) => $c['latest_message']['created_at'] ?? null)->values();
    }
}
