<?php

namespace App\Http\Controllers\Web;

use App\Ai\Agents\CommunityAssistant;
use App\Http\Controllers\Controller;
use App\Queries\AI\BuildAIContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Image;

class AIAssistantController extends Controller
{
    public function greet(Request $request, BuildAIContext $contextQuery): JsonResponse
    {
        $user    = $request->user();
        $context = $contextQuery->execute($user);
        $agent   = new CommunityAssistant($context);
        $prompt  = "The user just logged in. Introduce yourself as Curzzo, greet the user warmly by first name, then use your tools to check their communities and give ONE specific actionable recommendation (e.g. a pending lesson, a failed quiz to retake, or a badge to earn). Keep it to 2-3 sentences. No bullet points.";

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

        if ($this->isImageRequest($request->message)) {
            $imageResponse = Image::of($request->message)->size('3:2')->generate('openai');
            $img           = $imageResponse->firstImage();

            return response()->json([
                'type'    => 'image',
                'message' => "data:{$img->mime};base64,{$img->image}",
            ]);
        }

        $agent = new CommunityAssistant($context);

        $response = $request->conversation_id
            ? $agent->continue($request->conversation_id, as: $user)->prompt($request->message)
            : $agent->forUser($user)->prompt($request->message);

        return response()->json([
            'type'            => 'text',
            'message'         => $response->text,
            'conversation_id' => $response->conversationId,
        ]);
    }

    private function isImageRequest(string $message): bool
    {
        $lower = strtolower($message);

        $patterns = [
            '/\b(generate|create|make|draw|design|produce)\b.{0,30}\b(image|photo|picture|banner|thumbnail|cover|poster|visual|graphic|illustration)\b/',
            '/\b(image|photo|picture|banner|thumbnail|cover|poster|visual|graphic|illustration)\b.{0,30}\b(generate|create|make|draw|design)\b/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $lower)) {
                return true;
            }
        }

        return false;
    }
}
