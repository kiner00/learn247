<?php

namespace App\Http\Controllers\Web;

use App\Ai\Agents\CommunityChatbot;
use App\Http\Controllers\Controller;
use App\Models\Community;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityChatbotController extends Controller
{
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

        return response()->json([
            'message'         => $response->text,
            'conversation_id' => $response->conversationId,
        ]);
    }
}
