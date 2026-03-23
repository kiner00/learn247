<?php

namespace App\Http\Controllers\Api;

use App\Actions\Chat\DeleteChatMessage;
use App\Actions\Chat\SendChatMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Models\Community;
use App\Models\Message;
use App\Queries\Chat\GetChatMessages;
use App\Services\Community\MembershipAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request, Community $community, GetChatMessages $query, MembershipAccessService $membership): JsonResponse
    {
        $membership->assertMembership($request->user(), $community);

        $userId = $request->user()->id;
        $after  = (int) $request->query('after', 0);

        $messages = $after > 0
            ? $query->after($community, $after)
            : $query->latest($community);

        $query->markAsRead($community, $userId);

        return response()->json([
            'messages' => ChatMessageResource::collection($messages),
        ]);
    }

    public function store(SendMessageRequest $request, Community $community, SendChatMessage $action, MembershipAccessService $membership): JsonResponse
    {
        $membership->assertMembership($request->user(), $community);

        $message = $action->execute($request->user(), $community, $request->validated()['content']);

        return response()->json([
            'message' => new ChatMessageResource($message),
        ], 201);
    }

    public function poll(Request $request, Community $community, GetChatMessages $query, MembershipAccessService $membership): JsonResponse
    {
        $membership->assertMembership($request->user(), $community);

        $after    = (int) $request->query('after', 0);
        $messages = $query->after($community, $after)->map(fn ($m) => [
            'id'         => $m->id,
            'content'    => $m->content,
            'created_at' => $m->created_at,
            'user'       => [
                'id'       => $m->user->id,
                'name'     => $m->user->name,
                'username' => $m->user->username,
            ],
        ]);

        $query->markAsRead($community, $request->user()->id);

        return response()->json(['messages' => $messages]);
    }

    public function destroy(Request $request, Community $community, Message $message, DeleteChatMessage $action, MembershipAccessService $membership): JsonResponse
    {
        $membership->assertMembership($request->user(), $community);

        $action->execute($request->user(), $community, $message);

        return response()->json(['deleted' => $message->id]);
    }
}
