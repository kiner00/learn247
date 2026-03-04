<?php

namespace App\Http\Controllers\Web;

use App\Actions\Feed\CreateComment;
use App\Actions\Feed\DeleteComment;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Post $post, CreateComment $action): RedirectResponse
    {
        $request->validate(['content' => ['required', 'string']]);

        $action->execute($request->user(), $post, $request->only('content'));

        return back();
    }

    public function destroy(Comment $comment, DeleteComment $action): RedirectResponse
    {
        $action->execute(auth()->user(), $comment);

        return back();
    }
}
