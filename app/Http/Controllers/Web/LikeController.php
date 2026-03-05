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
        $existing = $post->likes()->where('user_id', $request->user()->id)->first();

        if ($existing) {
            $existing->delete();
        } else {
            $post->likes()->create(['user_id' => $request->user()->id]);
        }

        return back(fallback: route('communities.show', $post->community->slug));
    }

    public function toggleComment(Request $request, Comment $comment): RedirectResponse
    {
        $existing = $comment->likes()->where('user_id', $request->user()->id)->first();

        if ($existing) {
            $existing->delete();
        } else {
            $comment->likes()->create(['user_id' => $request->user()->id]);
        }

        return back();
    }
}
