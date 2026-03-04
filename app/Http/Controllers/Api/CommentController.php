<?php

namespace App\Http\Controllers\Api;

use App\Actions\Feed\CreateComment;
use App\Actions\Feed\DeleteComment;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    public function store(CreateCommentRequest $request, Post $post, CreateComment $action): CommentResource
    {
        $comment = $action->execute($request->user(), $post, $request->validated());

        return new CommentResource($comment->load('author'));
    }

    public function destroy(Comment $comment, DeleteComment $action): JsonResponse
    {
        $action->execute(auth()->user(), $comment);

        return response()->json(['message' => 'Comment deleted.']);
    }
}
