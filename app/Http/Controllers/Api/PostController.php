<?php

namespace App\Http\Controllers\Api;

use App\Actions\Feed\CreatePost;
use App\Actions\Feed\DeletePost;
use App\Actions\Feed\TogglePin;
use App\Actions\Feed\UpdatePost;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Community;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function store(CreatePostRequest $request, CreatePost $action): PostResource
    {
        $community = $request->validated('community_slug')
            ? Community::where('slug', $request->validated('community_slug'))->firstOrFail()
            : Community::findOrFail($request->validated('community_id'));
        $post      = $action->execute($request->user(), $community, $request->validated());

        return new PostResource($post->load('author'));
    }

    public function update(Request $request, Post $post, UpdatePost $action): JsonResponse
    {
        $data = $request->validate([
            'title'   => 'nullable|string|max:255',
            'content' => 'required|string|max:10000',
        ]);

        $action->execute($request->user(), $post, $data);

        return response()->json(['message' => 'Post updated.']);
    }

    public function destroy(Post $post, DeletePost $action): JsonResponse
    {
        $action->execute(auth()->user(), $post);

        return response()->json(['message' => 'Post deleted.']);
    }

    public function togglePin(Post $post, TogglePin $action): JsonResponse
    {
        $updated = $action->execute(auth()->user(), $post);

        return response()->json([
            'message'   => $updated->is_pinned ? 'Post pinned.' : 'Post unpinned.',
            'is_pinned' => $updated->is_pinned,
        ]);
    }
}
