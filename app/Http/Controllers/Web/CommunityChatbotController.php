<?php

namespace App\Http\Controllers\Web;

use App\Ai\Agents\CommunityChatbot;
use App\Http\Controllers\Controller;
use App\Models\ChatbotMessage;
use App\Models\Community;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityChatbotController extends Controller
{
    /**
     * Member sends a message — AI responds as the creator.
     */
    public function chat(Request $request, Community $community): JsonResponse
    {
        $request->validate([
            'message'         => ['required', 'string', 'max:1000'],
            'conversation_id' => ['nullable', 'string', 'uuid'],
        ]);

        $user  = $request->user();
        $community->load('owner:id,name');

        $agent = new CommunityChatbot($community);

        $response = $request->conversation_id
            ? $agent->continue($request->conversation_id, as: $user)->prompt($request->message)
            : $agent->forUser($user)->prompt($request->message);

        // Store both messages
        $attrs = [
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'conversation_id' => $response->conversationId,
        ];

        ChatbotMessage::insert([
            array_merge($attrs, ['role' => 'user',    'content' => $request->message, 'created_at' => now(), 'updated_at' => now()]),
            array_merge($attrs, ['role' => 'creator', 'content' => $response->text,   'created_at' => now(), 'updated_at' => now()]),
        ]);

        return response()->json([
            'message'         => $response->text,
            'conversation_id' => $response->conversationId,
        ]);
    }

    /**
     * Creator sends a real reply into a member's conversation.
     */
    public function reply(Request $request, Community $community): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $message = ChatbotMessage::create([
            'community_id'    => $community->id,
            'user_id'         => $request->user_id,
            'role'            => 'creator',
            'content'         => $request->message,
            'conversation_id' => null,
        ]);

        return response()->json([
            'message' => [
                'id'         => $message->id,
                'role'       => 'creator',
                'content'    => $message->content,
                'created_at' => $message->created_at,
            ],
        ]);
    }

    /**
     * Poll for new messages. Members see their own; creator can poll any user.
     */
    public function poll(Request $request, Community $community): JsonResponse
    {
        $after  = (int) $request->query('after', 0);
        $userId = $request->user()->id;

        // Creator can poll a specific user's conversation
        if ($request->query('user_id') && $request->user()->id === $community->owner_id) {
            $userId = (int) $request->query('user_id');
        }

        $messages = ChatbotMessage::where('community_id', $community->id)
            ->where('user_id', $userId)
            ->where('id', '>', $after)
            ->orderBy('id')
            ->select('id', 'role', 'content', 'created_at')
            ->get()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'role'       => $m->role,
                'text'       => $m->content,
                'content'    => $m->content,
                'created_at' => $m->created_at,
            ]);

        return response()->json(['messages' => $messages]);
    }

    /**
     * Load existing conversation history for member's sidebar.
     */
    public function history(Request $request, Community $community): JsonResponse
    {
        $messages = ChatbotMessage::where('community_id', $community->id)
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->select('id', 'role', 'content')
            ->limit(100)
            ->get()
            ->map(fn ($m) => [
                'id'   => $m->id,
                'role' => $m->role,
                'text' => $m->content,
            ]);

        return response()->json(['messages' => $messages]);
    }
}
