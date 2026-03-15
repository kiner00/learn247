<?php

namespace App\Http\Controllers\Web;

use App\Ai\Agents\CommunityAssistant;
use App\Http\Controllers\Controller;
use App\Queries\AI\BuildAIContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIAssistantController extends Controller
{
    public function greet(Request $request, BuildAIContext $contextQuery): JsonResponse
    {
        $user    = $request->user();
        $context = $contextQuery->execute($user);

        if (empty($context['communities'])) {
            return response()->json(['message' => "Hi {$user->name}! Join a community to get started.", 'conversation_id' => null]);
        }

        $agent    = new CommunityAssistant($context);
        $prompt   = "The user just logged in. Introduce yourself as Curzzo, greet the user warmly by first name, then give ONE specific actionable recommendation based on their current progress (e.g. a pending lesson, a failed quiz to retake, or a badge to earn). Keep it to 2-3 sentences. No bullet points.";
        $response = $agent->forUser($user)->prompt($prompt);

        return response()->json([
            'message'         => $response->text,
            'conversation_id' => $response->conversationId,
        ]);
    }

    public function chat(Request $request, BuildAIContext $contextQuery): JsonResponse
    {
        $request->validate([
            'message'         => ['required', 'string', 'max:1000'],
            'conversation_id' => ['nullable', 'string', 'uuid'],
        ]);

        $user    = $request->user();
        $context = $contextQuery->execute($user);

        if (empty($context['communities'])) {
            return response()->json(['error' => 'You must be a member of a community to use the AI assistant.'], 403);
        }

        $agent = new CommunityAssistant($context);

        $response = $request->conversation_id
            ? $agent->continue($request->conversation_id, as: $user)->prompt($request->message)
            : $agent->forUser($user)->prompt($request->message);

        return response()->json([
            'message'         => $response->text,
            'conversation_id' => $response->conversationId,
        ]);
    }
}
