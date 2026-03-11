<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\CommunityMember;
use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $partnerIds = DirectMessage::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->selectRaw('CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as partner_id', [$userId])
            ->distinct()
            ->pluck('partner_id');

        $conversations = $partnerIds->map(function ($partnerId) use ($userId) {
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
                'user'           => $partner,
                'latest_message' => $latest ? [
                    'content'    => $latest->content,
                    'created_at' => $latest->created_at,
                    'is_mine'    => $latest->sender_id === $userId,
                ] : null,
                'unread_count'   => $unread,
            ];
        })->sortByDesc(fn ($c) => $c['latest_message']['created_at'] ?? null)->values();

        return response()->json(['conversations' => $conversations]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $myId = $request->user()->id;

        $messages = DirectMessage::where(function ($q) use ($myId, $user) {
            $q->where('sender_id', $myId)->where('receiver_id', $user->id);
        })->orWhere(function ($q) use ($myId, $user) {
            $q->where('sender_id', $user->id)->where('receiver_id', $myId);
        })->oldest()->take(100)->get()->map(fn ($m) => [
            'id'         => $m->id,
            'content'    => $m->content,
            'is_mine'    => $m->sender_id === $myId,
            'created_at' => $m->created_at,
        ]);

        DirectMessage::where('sender_id', $user->id)
            ->where('receiver_id', $myId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'partner'  => ['id' => $user->id, 'name' => $user->name, 'username' => $user->username, 'avatar' => $user->avatar],
            'messages' => $messages,
        ]);
    }

    public function store(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:2000']]);

        $msg = DirectMessage::create([
            'sender_id'   => $request->user()->id,
            'receiver_id' => $user->id,
            'content'     => $data['content'],
        ]);

        return response()->json([
            'message' => [
                'id'         => $msg->id,
                'content'    => $msg->content,
                'is_mine'    => true,
                'created_at' => $msg->created_at,
            ],
        ], 201);
    }

    public function search(Request $request): JsonResponse
    {
        $q      = trim($request->query('q', ''));
        $userId = $request->user()->id;

        $communityIds = CommunityMember::where('user_id', $userId)->pluck('community_id');

        $users = User::select('id', 'name', 'username', 'avatar')
            ->whereHas('communityMemberships', fn ($q2) => $q2->whereIn('community_id', $communityIds))
            ->where('id', '!=', $userId)
            ->when($q, fn ($q2) => $q2->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('username', 'like', "%{$q}%");
            }))
            ->limit(10)
            ->get();

        return response()->json(['users' => $users]);
    }
}
