<?php

namespace App\Http\Controllers\Api;

use App\Actions\Feed\ToggleLike;
use App\Actions\Feed\TogglePin;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function togglePost(Request $request, Post $post, ToggleLike $action): JsonResponse
    {
        $result = $action->execute($request->user(), $post, $request->input('type', 'like'));

        return response()->json($result);
    }

    public function toggleComment(Request $request, Comment $comment, ToggleLike $action): JsonResponse
    {
        $result = $action->execute($request->user(), $comment, $request->input('type', 'like'));

        return response()->json($result);
    }

    public function togglePin(Request $request, Post $post, TogglePin $action): JsonResponse
    {
        $updated = $action->execute($request->user(), $post);

        return response()->json([
            'message'   => $updated->is_pinned ? 'Post pinned.' : 'Post unpinned.',
            'is_pinned' => $updated->is_pinned,
        ]);
    }
}
