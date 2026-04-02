<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityDirectMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityDmController extends Controller
{
    /**
     * Get conversation list — users the current user has chatted with in this community.
     */
    public function conversations(Request $request, Community $community): JsonResponse
    {
        $userId = $request->user()->id;

        $users = CommunityDirectMessage::where('community_id', $community->id)
            ->where(fn ($q) => $q->where('sender_id', $userId)->orWhere('receiver_id', $userId))
            ->selectRaw("
                CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as other_user_id,
                MAX(created_at) as last_message_at,
                COUNT(*) as message_count
            ", [$userId])
            ->groupByRaw("CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END", [$userId])
            ->orderByDesc('last_message_at')
            ->get();

        $userIds = $users->pluck('other_user_id');
        $userMap = \App\Models\User::whereIn('id', $userIds)
            ->select('id', 'name', 'avatar')
            ->get()
            ->keyBy('id');

        $result = $users->map(fn ($row) => [
            'id'              => $row->other_user_id,
            'name'            => $userMap[$row->other_user_id]->name ?? 'Unknown',
            'avatar'          => $userMap[$row->other_user_id]->avatar ?? null,
            'last_message_at' => $row->last_message_at,
            'message_count'   => $row->message_count,
        ])->values();

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
                'id'         => $m->id,
                'sender_id'  => $m->sender_id,
                'content'    => $m->content,
                'is_mine'    => $m->sender_id === $myId,
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
            'content'     => ['required', 'string', 'max:2000'],
        ]);

        $message = CommunityDirectMessage::create([
            'community_id' => $community->id,
            'sender_id'    => $request->user()->id,
            'receiver_id'  => $request->receiver_id,
            'content'      => $request->content,
        ]);

        return response()->json([
            'message' => [
                'id'         => $message->id,
                'sender_id'  => $message->sender_id,
                'content'    => $message->content,
                'is_mine'    => true,
                'created_at' => $message->created_at,
            ],
        ]);
    }

    /**
     * Poll for new messages in a conversation.
     */
    public function poll(Request $request, Community $community, int $userId): JsonResponse
    {
        $myId  = $request->user()->id;
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
                'id'         => $m->id,
                'sender_id'  => $m->sender_id,
                'content'    => $m->content,
                'is_mine'    => $m->sender_id === $myId,
                'created_at' => $m->created_at,
            ]);

        return response()->json(['messages' => $messages]);
    }
}
