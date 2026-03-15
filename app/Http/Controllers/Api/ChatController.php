<?php

namespace App\Http\Controllers\Api;

use App\Actions\Chat\DeleteChatMessage;
use App\Actions\Chat\SendChatMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Message;
use App\Models\Subscription;
use App\Queries\Chat\GetChatMessages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request, Community $community, GetChatMessages $query): JsonResponse
    {
        $this->requireMembership($request, $community);

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

    public function store(SendMessageRequest $request, Community $community, SendChatMessage $action): JsonResponse
    {
        $this->requireMembership($request, $community);

        $message = $action->execute($request->user(), $community, $request->validated()['content']);

        return response()->json([
            'message' => new ChatMessageResource($message),
        ], 201);
    }

    public function poll(Request $request, Community $community, GetChatMessages $query): JsonResponse
    {
        $this->requireMembership($request, $community);

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

    public function destroy(Request $request, Community $community, Message $message, DeleteChatMessage $action): JsonResponse
    {
        $this->requireMembership($request, $community);

        $action->execute($request->user(), $community, $message);

        return response()->json(['deleted' => $message->id]);
    }

    private function requireMembership(Request $request, Community $community): void
    {
        $user = $request->user();

        if ($community->owner_id === $user->id) {
            return;
        }

        if ($community->isFree()) {
            abort_unless(
                CommunityMember::where('community_id', $community->id)->where('user_id', $user->id)->exists(),
                403,
                'You must be a member of this community.'
            );
            return;
        }

        abort_unless(
            Subscription::where('community_id', $community->id)
                ->where('user_id', $user->id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists(),
            403,
            'An active membership is required.'
        );
    }
}
