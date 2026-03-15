<?php

namespace App\Http\Controllers\Web;

use App\Actions\Feed\ToggleLike;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function togglePost(Request $request, Post $post, ToggleLike $action): RedirectResponse
    {
        $action->execute($request->user(), $post, $request->input('type', 'like'));

        return back(fallback: route('communities.show', $post->community->slug));
    }

    public function toggleComment(Request $request, Comment $comment, ToggleLike $action): RedirectResponse
    {
        $action->execute($request->user(), $comment, $request->input('type', 'like'));

        return back();
    }
}
