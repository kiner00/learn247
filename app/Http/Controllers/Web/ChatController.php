<?php

namespace App\Http\Controllers\Web;

use App\Actions\Chat\DeleteChatMessage;
use App\Actions\Chat\SendChatMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Models\ChatbotMessage;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Message;
use App\Queries\Chat\GetChatMessages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Contracts\TelegramGateway;
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
        $telegramMemberCount = $telegramConnected
            ? app(TelegramGateway::class)->getChatMemberCount($community->telegram_bot_token, $community->telegram_chat_id)
            : null;
        $isOwner = $userId && $userId === $community->owner_id;

        // For creator: load chatbot conversation users
        $chatbotUsers = [];
        if ($isOwner) {
            $rows = ChatbotMessage::where('community_id', $community->id)
                ->selectRaw('user_id, MAX(created_at) as last_chat_at, COUNT(*) as message_count')
                ->groupBy('user_id')
                ->orderByDesc('last_chat_at')
                ->get();

            $userIds  = $rows->pluck('user_id');
            $userMap  = \App\Models\User::whereIn('id', $userIds)->select('id', 'name', 'avatar')->get()->keyBy('id');

            $chatbotUsers = $rows->map(fn ($row) => [
                'id'            => $row->user_id,
                'name'          => $userMap[$row->user_id]->name ?? 'Unknown',
                'avatar'        => $userMap[$row->user_id]->avatar ?? null,
                'last_chat_at'  => $row->last_chat_at,
                'message_count' => $row->message_count,
            ])->values();
        }

        // If linking to a specific user's chat (from Members page)
        $selectedChatUser = null;
        if (request()->query('user')) {
            $targetUserId = (int) request()->query('user');
            $targetUser = \App\Models\User::select('id', 'name', 'avatar')->find($targetUserId);
            if ($targetUser) {
                $selectedChatUser = [
                    'id'     => $targetUser->id,
                    'name'   => $targetUser->name,
                    'avatar' => $targetUser->avatar,
                ];
            }
        }

        return Inertia::render('Communities/Chat', compact(
            'community', 'messages', 'affiliate', 'telegramConnected', 'telegramMemberCount', 'isOwner', 'chatbotUsers', 'selectedChatUser'
        ));
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
