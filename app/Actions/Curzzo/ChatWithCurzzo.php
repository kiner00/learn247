<?php

namespace App\Actions\Curzzo;

use App\Ai\Agents\CurzzoBot;
use App\Models\Community;
use App\Models\Curzzo;
use App\Models\CurzzoMessage;
use App\Models\User;
use App\Services\Community\CurzzoAccessService;
use App\Services\Community\CurzzoLimitService;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatWithCurzzo
{
    public function __construct(
        private CurzzoLimitService $limits,
        private CurzzoAccessService $access,
    ) {}

    /**
     * Returns a ChatResult with HTTP status + JSON body. Both Web and API
     * controllers wrap it identically into a response.
     */
    public function execute(
        User $user,
        Community $community,
        Curzzo $curzzo,
        string $message,
        ?string $conversationId = null,
    ): ChatResult {
        $context = $this->access->buildContext($user, $community, collect([$curzzo->id]));
        if (! $this->access->hasAccess($curzzo, $context)) {
            return new ChatResult(403, ['error' => 'Purchase required to chat with this Curzzo.']);
        }

        // Daily limit check
        $limitCheck = $this->limits->canSendMessage($user, $community);
        if (! $limitCheck['allowed']) {
            return new ChatResult(429, [
                'error' => $limitCheck['reason'],
                'limit_reached' => true,
                'daily_limit' => $limitCheck['daily_limit'],
                'daily_used' => $limitCheck['daily_used'],
                'topup_remaining' => $limitCheck['topup_remaining'],
            ]);
        }

        $community->loadMissing('owner:id,name');

        $agent = $this->makeAgent($curzzo, $community);

        try {
            $response = $conversationId
                ? $agent->continue($conversationId, as: $user)->prompt($message)
                : $agent->forUser($user)->prompt($message);
        } catch (Throwable $e) {
            Log::error('CurzzoChat agent call failed', [
                'curzzo_id' => $curzzo->id,
                'community_id' => $community->id,
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'model_tier' => $curzzo->model_tier,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            \Sentry\captureException($e);

            return new ChatResult(503, [
                'error' => 'The bot had trouble responding. Please try again.',
                'conversation_id' => $conversationId,
            ]);
        }

        $now = now();
        $attrs = [
            'curzzo_id' => $curzzo->id,
            'community_id' => $community->id,
            'user_id' => $user->id,
            'conversation_id' => $response->conversationId,
        ];

        CurzzoMessage::insert([
            array_merge($attrs, ['role' => 'user',      'content' => $message,        'created_at' => $now, 'updated_at' => $now]),
            array_merge($attrs, ['role' => 'assistant', 'content' => $response->text, 'created_at' => $now, 'updated_at' => $now]),
        ]);

        if (! empty($limitCheck['using_topup'])) {
            $this->limits->consumeTopup($user, $community);
        }

        $newCheck = $this->limits->canSendMessage($user, $community);

        return new ChatResult(200, [
            'message' => $response->text,
            'conversation_id' => $response->conversationId,
            'daily_limit' => $newCheck['daily_limit'],
            'daily_used' => $newCheck['daily_used'],
            'topup_remaining' => $newCheck['topup_remaining'],
        ]);
    }

    /**
     * Agent factory — extracted as a seam so tests can substitute a stub.
     * Returns a `CurzzoBot` in production; widened to `object` so test
     * doubles (anonymous classes) can satisfy the contract too.
     */
    protected function makeAgent(Curzzo $curzzo, Community $community): object
    {
        return new CurzzoBot($curzzo, $community);
    }
}
