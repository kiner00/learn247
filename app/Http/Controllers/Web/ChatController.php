<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function index(Community $community): Response
    {
        $userId = auth()->id();

        $messages = Message::where('community_id', $community->id)
            ->with('user:id,name,username')
            ->latest()
            ->take(50)
            ->get()
            ->reverse()
            ->values();

        // Mark messages as read
        if ($userId) {
            $community->members()->where('user_id', $userId)->update([
                'messages_last_read_at' => now(),
            ]);
        }

        $community->loadCount('members');

        $affiliate = $userId ? $community->affiliates()->where('user_id', $userId)->first() : null;

        return Inertia::render('Communities/Chat', compact('community', 'messages', 'affiliate'));
    }

    public function store(Request $request, Community $community): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $message = Message::create([
            'community_id' => $community->id,
            'user_id'      => $request->user()->id,
            'content'      => $data['content'],
        ]);

        // Mark sender's own messages as read too
        $community->members()->where('user_id', $request->user()->id)->update([
            'messages_last_read_at' => now(),
        ]);

        $message->load('user:id,name,username');

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

    public function poll(Request $request, Community $community): JsonResponse
    {
        $after = (int) $request->query('after', 0);

        $messages = Message::where('community_id', $community->id)
            ->where('id', '>', $after)
            ->with('user:id,name,username')
            ->oldest()
            ->take(50)
            ->get()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'content'    => $m->content,
                'created_at' => $m->created_at,
                'user'       => [
                    'id'       => $m->user->id,
                    'name'     => $m->user->name,
                    'username' => $m->user->username,
                ],
            ]);

        // Mark as read on poll
        if (auth()->id()) {
            $community->members()->where('user_id', auth()->id())->update([
                'messages_last_read_at' => now(),
            ]);
        }

        return response()->json(['messages' => $messages]);
    }
}
