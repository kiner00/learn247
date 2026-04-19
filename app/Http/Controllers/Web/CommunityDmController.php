<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityDirectMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CommunityDmController extends Controller
{
    /**
     * Get conversation list — users the current user has chatted with in this community.
     */
    public function conversations(Request $request, Community $community): JsonResponse
    {
        $userId = $request->user()->id;

        // Get all unique user IDs this user has chatted with
        $sentTo = CommunityDirectMessage::where('community_id', $community->id)
            ->where('sender_id', $userId)
            ->distinct()
            ->pluck('receiver_id');

        $receivedFrom = CommunityDirectMessage::where('community_id', $community->id)
            ->where('receiver_id', $userId)
            ->distinct()
            ->pluck('sender_id');

        $otherUserIds = $sentTo->merge($receivedFrom)->unique()->values();

        if ($otherUserIds->isEmpty()) {
            return response()->json(['conversations' => []]);
        }

        $users = User::whereIn('id', $otherUserIds)
            ->select('id', 'name', 'avatar')
            ->get();

        $result = $users->map(function ($user) use ($community, $userId) {
            $count = CommunityDirectMessage::where('community_id', $community->id)
                ->where(fn ($q) => $q
                    ->where(fn ($q2) => $q2->where('sender_id', $userId)->where('receiver_id', $user->id))
                    ->orWhere(fn ($q2) => $q2->where('sender_id', $user->id)->where('receiver_id', $userId))
                )
                ->count();

            $lastMessage = CommunityDirectMessage::where('community_id', $community->id)
                ->where(fn ($q) => $q
                    ->where(fn ($q2) => $q2->where('sender_id', $userId)->where('receiver_id', $user->id))
                    ->orWhere(fn ($q2) => $q2->where('sender_id', $user->id)->where('receiver_id', $userId))
                )
                ->latest()
                ->value('created_at');

            return [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
                'last_message_at' => $lastMessage,
                'message_count' => $count,
            ];
        })->sortByDesc('last_message_at')->values();

        return response()->json(['conversations' => $result]);
    }

    /**
     * Get messages between current user and another user.
     */
    public function messages(Request $request, Community $community, int $userId): JsonResponse
    {
        $myId = $request->user()->id;

        $messages = CommunityDirectMessage::where('community_id', $community->id)
            ->where(fn ($q) => $q
                ->where(fn ($q2) => $q2->where('sender_id', $myId)->where('receiver_id', $userId))
                ->orWhere(fn ($q2) => $q2->where('sender_id', $userId)->where('receiver_id', $myId))
            )
            ->orderBy('created_at')
            ->select('id', 'sender_id', 'receiver_id', 'content', 'created_at')
            ->limit(200)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'sender_id' => $m->sender_id,
                'content' => $m->content,
                'is_mine' => $m->sender_id === $myId,
                'created_at' => $m->created_at,
            ]);

        return response()->json(['messages' => $messages]);
    }

    /**
     * Send a message to another user within this community.
     */
    public function send(Request $request, Community $community): JsonResponse
    {
        $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $message = CommunityDirectMessage::create([
            'community_id' => $community->id,
            'sender_id' => $request->user()->id,
            'receiver_id' => $request->receiver_id,
            'content' => $request->content,
        ]);

        Log::info('DM sent', ['id' => $message->id, 'community' => $community->id, 'from' => $request->user()->id, 'to' => $request->receiver_id]);

        return response()->json([
            'message' => [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'content' => $message->content,
                'is_mine' => true,
                'created_at' => $message->created_at,
            ],
        ]);
    }

    /**
     * Poll for new messages in a conversation.
     */
    public function poll(Request $request, Community $community, int $userId): JsonResponse
    {
        $myId = $request->user()->id;
        $after = (int) $request->query('after', 0);

        $messages = CommunityDirectMessage::where('community_id', $community->id)
            ->where('id', '>', $after)
            ->where(fn ($q) => $q
                ->where(fn ($q2) => $q2->where('sender_id', $myId)->where('receiver_id', $userId))
                ->orWhere(fn ($q2) => $q2->where('sender_id', $userId)->where('receiver_id', $myId))
            )
            ->orderBy('id')
            ->select('id', 'sender_id', 'receiver_id', 'content', 'created_at')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'sender_id' => $m->sender_id,
                'content' => $m->content,
                'is_mine' => $m->sender_id === $myId,
                'created_at' => $m->created_at,
            ]);

        return response()->json(['messages' => $messages]);
    }
}
