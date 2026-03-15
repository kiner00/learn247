<?php

namespace App\Http\Controllers\Api;

use App\Actions\DirectMessage\SendDirectMessage;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\DirectMessage\GetConversations;
use App\Queries\DirectMessage\GetConversationThread;
use App\Queries\DirectMessage\SearchMessageableUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectMessageController extends Controller
{
    public function index(Request $request, GetConversations $query): JsonResponse
    {
        return response()->json(['conversations' => $query->execute($request->user()->id)]);
    }

    public function show(Request $request, User $user, GetConversationThread $query): JsonResponse
    {
        $data = $query->execute($request->user()->id, $user->id);

        return response()->json([
            'partner'  => ['id' => $user->id, 'name' => $user->name, 'username' => $user->username, 'avatar' => $user->avatar],
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
        ], 201);
    }

    public function search(Request $request, SearchMessageableUsers $query): JsonResponse
    {
        $users = $query->execute($request->user()->id, trim($request->query('q', '')));

        return response()->json(['users' => $users]);
    }
}
