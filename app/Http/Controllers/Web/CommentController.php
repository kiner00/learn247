<?php

namespace App\Http\Controllers\Web;

use App\Actions\Feed\CreateComment;
use App\Actions\Feed\DeleteComment;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCommentRequest;
use App\Models\Comment;
use App\Models\CommunityMember;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    public function store(CreateCommentRequest $request, Post $post, CreateComment $action): RedirectResponse
    {
        $member = CommunityMember::where('community_id', $post->community_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($member?->is_blocked) {
            return back()->withErrors(['blocked' => 'You have been blocked from commenting in this community.']);
        }

        $action->execute($request->user(), $post, $request->validated());

        return back();
    }

    public function destroy(Comment $comment, DeleteComment $action): RedirectResponse
    {
        $action->execute(auth()->user(), $comment);

        return back();
    }
}
