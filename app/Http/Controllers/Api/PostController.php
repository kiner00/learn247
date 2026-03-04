<?php

namespace App\Http\Controllers\Api;

use App\Actions\Feed\CreatePost;
use App\Actions\Feed\DeletePost;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Community;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

class PostController extends Controller
{
    public function store(CreatePostRequest $request, CreatePost $action): PostResource
    {
        $community = Community::findOrFail($request->validated('community_id'));
        $post      = $action->execute($request->user(), $community, $request->validated());

        return new PostResource($post->load('author'));
    }

    public function destroy(Post $post, DeletePost $action): JsonResponse
    {
        $action->execute(auth()->user(), $post);

        return response()->json(['message' => 'Post deleted.']);
    }
}
