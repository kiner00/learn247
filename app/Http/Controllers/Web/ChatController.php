<?php

namespace App\Http\Controllers\Web;

use App\Actions\Chat\DeleteChatMessage;
use App\Actions\Chat\SendChatMessage;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Message;
use App\Queries\Chat\GetChatMessages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $community->loadCount('members');
        $affiliate = $userId ? $community->affiliates()->where('user_id', $userId)->first() : null;

        return Inertia::render('Communities/Chat', compact('community', 'messages', 'affiliate'));
    }

    public function store(Request $request, Community $community, SendChatMessage $action): JsonResponse
    {
        $data    = $request->validate(['content' => ['required', 'string', 'max:2000']]);
        $message = $action->execute($request->user(), $community, $data['content']);

        return response()->json([
            'message' => [
                'id'         => $message->id,
                'content'    => $message->content,
                'created_at' => $message->created_at,
                'user'       => [
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
            'id'         => $m->id,
            'content'    => $m->content,
            'created_at' => $m->created_at,
            'user'       => [
                'id'       => $m->user->id,
                'name'     => $m->user->name,
                'username' => $m->user->username,
            ],
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
