<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function togglePost(Request $request, Post $post): RedirectResponse
    {
        $type     = in_array($request->input('type'), ['like', 'handshake', 'trophy'])
                        ? $request->input('type')
                        : 'like';
        $existing = $post->likes()->where('user_id', $request->user()->id)->first();

        if ($existing && $existing->type === $type) {
            $existing->delete();
        } elseif ($existing) {
            $existing->update(['type' => $type]);
        } else {
            $post->likes()->create(['user_id' => $request->user()->id, 'type' => $type]);
        }

        return back(fallback: route('communities.show', $post->community->slug));
    }

    public function toggleComment(Request $request, Comment $comment): RedirectResponse
    {
        $type     = in_array($request->input('type'), ['like', 'handshake', 'trophy'])
                        ? $request->input('type')
                        : 'like';
        $existing = $comment->likes()->where('user_id', $request->user()->id)->first();

        if ($existing && $existing->type === $type) {
            $existing->delete();
        } elseif ($existing) {
            $existing->update(['type' => $type]);
        } else {
            $comment->likes()->create(['user_id' => $request->user()->id, 'type' => $type]);
        }

        return back();
    }
}
