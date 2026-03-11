<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function togglePost(Request $request, Post $post): JsonResponse
    {
        $type = in_array($request->input('type'), ['like', 'handshake', 'trophy'])
            ? $request->input('type')
            : 'like';

        $existing = $post->likes()->where('user_id', $request->user()->id)->first();

        if ($existing && $existing->type === $type) {
            $existing->delete();
            $action = 'removed';
        } elseif ($existing) {
            $existing->update(['type' => $type]);
            $action = 'updated';
        } else {
            $post->likes()->create(['user_id' => $request->user()->id, 'type' => $type]);
            $action = 'added';
        }

        return response()->json([
            'action'      => $action,
            'type'        => $type,
            'likes_count' => $post->likes()->count(),
        ]);
    }

    public function toggleComment(Request $request, Comment $comment): JsonResponse
    {
        $type = in_array($request->input('type'), ['like', 'handshake', 'trophy'])
            ? $request->input('type')
            : 'like';

        $existing = $comment->likes()->where('user_id', $request->user()->id)->first();

        if ($existing && $existing->type === $type) {
            $existing->delete();
            $action = 'removed';
        } elseif ($existing) {
            $existing->update(['type' => $type]);
            $action = 'updated';
        } else {
            $comment->likes()->create(['user_id' => $request->user()->id, 'type' => $type]);
            $action = 'added';
        }

        return response()->json([
            'action'      => $action,
            'type'        => $type,
            'likes_count' => $comment->likes()->count(),
        ]);
    }

    public function togglePin(Request $request, Post $post): JsonResponse
    {
        $user      = $request->user();
        $community = $post->community;

        $membership = $community->members()->where('user_id', $user->id)->first();
        $isAdmin    = $community->owner_id === $user->id || $membership?->role === 'admin';

        abort_unless($isAdmin, 403);

        $post->update(['is_pinned' => ! $post->is_pinned]);

        return response()->json(['is_pinned' => $post->is_pinned]);
    }
}
