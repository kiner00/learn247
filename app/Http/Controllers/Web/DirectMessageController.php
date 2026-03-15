<?php

namespace App\Http\Controllers\Web;

use App\Actions\DirectMessage\DeleteDirectMessage;
use App\Actions\DirectMessage\SendDirectMessage;
use App\Http\Controllers\Controller;
use App\Models\DirectMessage;
use App\Models\User;
use App\Queries\DirectMessage\GetConversations;
use App\Queries\DirectMessage\GetConversationThread;
use App\Queries\DirectMessage\SearchMessageableUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DirectMessageController extends Controller
{
    public function search(Request $request, SearchMessageableUsers $query): JsonResponse
    {
        $users = $query->execute($request->user()->id, trim($request->query('q', '')));

        return response()->json(['users' => $users]);
    }

    public function index(Request $request, GetConversations $query): Response|JsonResponse
    {
        $conversations = $query->execute($request->user()->id);

        if ($request->wantsJson()) {
            return response()->json(['conversations' => $conversations]);
        }

        return Inertia::render('Messages/Index', compact('conversations'));
    }

    public function show(Request $request, User $user, GetConversationThread $query): Response
    {
        $data = $query->execute($request->user()->id, $user->id);

        return Inertia::render('Messages/Show', [
            'partner'  => ['id' => $user->id, 'name' => $user->name, 'username' => $user->username],
            'messages' => $data['messages'],
        ]);
    }

    public function store(Request $request, User $user, SendDirectMessage $action): JsonResponse
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:2000']]);
        $msg  = $action->execute($request->user(), $user, $data['content']);

        return response()->json([
            'message' => [
                'id'         => $msg->id,
                'content'    => $msg->content,
                'is_mine'    => true,
                'created_at' => $msg->created_at,
            ],
        ]);
    }

    public function poll(Request $request, User $user): JsonResponse
    {
        $myId  = $request->user()->id;
        $after = (int) $request->query('after', 0);

        $messages = DirectMessage::where('sender_id', $user->id)
            ->where('receiver_id', $myId)
            ->where('id', '>', $after)
            ->oldest()
            ->take(50)
            ->get()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'content'    => $m->content,
                'is_mine'    => false,
                'created_at' => $m->created_at,
            ]);

        if ($messages->isNotEmpty()) {
            DirectMessage::where('sender_id', $user->id)
                ->where('receiver_id', $myId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return response()->json(['messages' => $messages]);
    }

    public function destroy(Request $request, DirectMessage $directMessage, DeleteDirectMessage $action): JsonResponse
    {
        $action->execute($request->user(), $directMessage);

        return response()->json(['deleted' => $directMessage->id]);
    }
}
