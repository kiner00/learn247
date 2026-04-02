<?php

namespace App\Http\Controllers\Web;

use App\Actions\Chat\DeleteChatMessage;
use App\Actions\Chat\SendChatMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Message;
use App\Queries\Chat\GetChatMessages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\StorageService;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function index(Community $community, GetChatMessages $query): Response
    {
        $userId   = auth()->id();
        $messages = $query->latest($community);

        if ($userId) {
            $query->markAsRead($community, $userId);
        }

        $community->loadCount('members')->load('owner:id,name,avatar');
        $affiliate = $userId ? $community->affiliates()->where('user_id', $userId)->first() : null;

        $telegramConnected = (bool) ($community->telegram_bot_token && $community->telegram_chat_id);

        return Inertia::render('Communities/Chat', compact('community', 'messages', 'affiliate', 'telegramConnected'));
    }

    public function store(SendMessageRequest $request, Community $community, SendChatMessage $action): JsonResponse
    {
        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($member?->is_blocked) {
            return response()->json(['error' => 'You have been blocked from chatting in this community.'], 403);
        }

        $mediaUrl  = null;
        $mediaType = null;

        if ($request->hasFile('media')) {
            $file      = $request->file('media');
            $mediaUrl  = app(StorageService::class)->upload($file, 'chat-media');
            $mediaType = str_starts_with($file->getMimeType(), 'video/') ? 'video' : 'image';
        }

        $message = $action->execute($request->user(), $community, $request->validated()['content'] ?? '', $mediaUrl, $mediaType);

        return response()->json([
            'message' => [
                'id'              => $message->id,
                'content'         => $message->content,
                'created_at'      => $message->created_at,
                'telegram_author' => null,
                'media_url'       => $message->media_url,
                'media_type'      => $message->media_type,
                'user'            => [
                    'id'       => $message->user->id,
                    'name'     => $message->user->name,
                    'username' => $message->user->username,
                ],
            ],
        ]);
    }

    public function poll(Request $request, Community $community, GetChatMessages $query): JsonResponse
    {
        $after    = (int) $request->query('after', 0);
        $messages = $query->after($community, $after)->map(fn ($m) => [
            'id'              => $m->id,
            'content'         => $m->content,
            'created_at'      => $m->created_at,
            'telegram_author' => $m->telegram_author,
            'media_url'       => $m->media_url,
            'media_type'      => $m->media_type,
            'user'            => $m->user ? [
                'id'       => $m->user->id,
                'name'     => $m->user->name,
                'username' => $m->user->username,
            ] : null,
        ]);

        if (auth()->id()) {
            $query->markAsRead($community, auth()->id());
        }

        return response()->json(['messages' => $messages]);
    }

    public function destroy(Request $request, Community $community, Message $message, DeleteChatMessage $action): JsonResponse
    {
        $action->execute($request->user(), $community, $message);

        return response()->json(['deleted' => $message->id]);
    }
}
