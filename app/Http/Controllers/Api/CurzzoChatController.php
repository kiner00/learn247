<?php

namespace App\Http\Controllers\Api;

use App\Actions\Curzzo\ChatWithCurzzo;
use App\Actions\Curzzo\GetCurzzoChatHistory;
use App\Actions\Curzzo\ResetCurzzoChatHistory;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChatWithCurzzoRequest;
use App\Http\Resources\CurzzoChatMessageResource;
use App\Models\Community;
use App\Models\Curzzo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CurzzoChatController extends Controller
{
    public function chat(ChatWithCurzzoRequest $request, Community $community, Curzzo $curzzo, ChatWithCurzzo $action): JsonResponse
    {
        abort_unless($curzzo->community_id === $community->id && $curzzo->is_active, 404);

        $result = $action->execute(
            $request->user(),
            $community,
            $curzzo,
            $request->validated('message'),
            $request->validated('conversation_id'),
        );

        return response()->json($result->body, $result->status);
    }

    public function history(Request $request, Community $community, Curzzo $curzzo, GetCurzzoChatHistory $action): AnonymousResourceCollection
    {
        abort_unless($curzzo->community_id === $community->id, 404);

        return CurzzoChatMessageResource::collection(
            $action->execute($request->user(), $curzzo)
        );
    }

    public function resetHistory(Request $request, Community $community, Curzzo $curzzo, ResetCurzzoChatHistory $action): JsonResponse
    {
        abort_unless($curzzo->community_id === $community->id, 404);

        $action->execute($request->user(), $curzzo);

        return response()->json(['ok' => true]);
    }
}
