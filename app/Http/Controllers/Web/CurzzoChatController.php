<?php

namespace App\Http\Controllers\Web;

use App\Ai\Agents\CurzzoBot;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Curzzo;
use App\Models\CurzzoMessage;
use App\Models\CurzzoPurchase;
use App\Services\Community\CurzzoLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CurzzoChatController extends Controller
{
    public function chat(Request $request, Community $community, Curzzo $curzzo, CurzzoLimitService $limits): JsonResponse
    {
        abort_unless($curzzo->community_id === $community->id && $curzzo->is_active, 404);

        // Access check for paid Curzzos
        if (! $curzzo->isFree()) {
            $hasAccess = CurzzoPurchase::where('curzzo_id', $curzzo->id)
                ->where('user_id', $request->user()->id)
                ->where('status', CurzzoPurchase::STATUS_PAID)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();

            if (! $hasAccess && $request->user()->id !== $community->owner_id) {
                return response()->json(['error' => 'Purchase required to chat with this Curzzo.'], 403);
            }
        }

        // Daily limit check
        $limitCheck = $limits->canSendMessage($request->user(), $community);
        if (! $limitCheck['allowed']) {
            return response()->json([
                'error'           => $limitCheck['reason'],
                'limit_reached'   => true,
                'daily_limit'     => $limitCheck['daily_limit'],
                'daily_used'      => $limitCheck['daily_used'],
                'topup_remaining' => $limitCheck['topup_remaining'],
            ], 429);
        }

        $request->validate([
            'message'         => ['required', 'string', 'max:1000'],
            'conversation_id' => ['nullable', 'string', 'uuid'],
        ]);

        $user = $request->user();
        $community->load('owner:id,name');

        $agent = new CurzzoBot($curzzo, $community);

        try {
            $response = $request->conversation_id
                ? $agent->continue($request->conversation_id, as: $user)->prompt($request->message)
                : $agent->forUser($user)->prompt($request->message);
        } catch (Throwable $e) {
            Log::error('CurzzoChat agent call failed', [
                'curzzo_id'       => $curzzo->id,
                'community_id'    => $community->id,
                'user_id'         => $user->id,
                'conversation_id' => $request->conversation_id,
                'model_tier'      => $curzzo->model_tier,
                'error'           => $e->getMessage(),
                'exception'       => get_class($e),
            ]);

            return response()->json([
                'error'           => 'The bot had trouble responding. Please try again.',
                'conversation_id' => $request->conversation_id,
            ], 503);
        }

        $attrs = [
            'curzzo_id'       => $curzzo->id,
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'conversation_id' => $response->conversationId,
        ];

        CurzzoMessage::insert([
            array_merge($attrs, ['role' => 'user',      'content' => $request->message, 'created_at' => now(), 'updated_at' => now()]),
            array_merge($attrs, ['role' => 'assistant',  'content' => $response->text,   'created_at' => now(), 'updated_at' => now()]),
        ]);

        // Consume topup if over daily limit
        if (! empty($limitCheck['using_topup'])) {
            $limits->consumeTopup($user, $community);
        }

        // Fresh usage after message sent
        $newCheck = $limits->canSendMessage($user, $community);

        return response()->json([
            'message'         => $response->text,
            'conversation_id' => $response->conversationId,
            'daily_limit'     => $newCheck['daily_limit'],
            'daily_used'      => $newCheck['daily_used'],
            'topup_remaining' => $newCheck['topup_remaining'],
        ]);
    }

    public function history(Request $request, Community $community, Curzzo $curzzo): JsonResponse
    {
        abort_unless($curzzo->community_id === $community->id, 404);

        $messages = CurzzoMessage::where('curzzo_id', $curzzo->id)
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->select('id', 'role', 'content')
            ->limit(100)
            ->get()
            ->map(fn ($m) => [
                'id'   => $m->id,
                'role' => $m->role,
                'text' => $m->content,
            ]);

        return response()->json(['messages' => $messages]);
    }
}
