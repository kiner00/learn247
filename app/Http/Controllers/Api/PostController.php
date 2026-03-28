<?php

namespace App\Http\Controllers\Api;

use App\Actions\Feed\CreatePost;
use App\Actions\Feed\DeletePost;
use App\Actions\Feed\TogglePin;
use App\Actions\Feed\UpdatePost;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Community;
use App\Models\Post;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

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

    public function update(UpdatePostRequest $request, Post $post, UpdatePost $action): JsonResponse
    {
        try {
            $action->execute($request->user(), $post, $request->validated());

            return response()->json(['message' => 'Post updated.']);
        } catch (\Throwable $e) {
            Log::error('Api\PostController@update failed', ['error' => $e->getMessage(), 'post_id' => $post->id]);
            return response()->json(['message' => 'Failed to update post.'], 500);
        }
    }

    public function destroy(Post $post, DeletePost $action): JsonResponse
    {
        try {
            $action->execute(auth()->user(), $post);

            return response()->json(['message' => 'Post deleted.']);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Api\PostController@destroy failed', ['error' => $e->getMessage(), 'post_id' => $post->id]);
            return response()->json(['message' => 'Failed to delete post.'], 500);
        }
    }

    public function togglePin(Post $post, TogglePin $action): JsonResponse
    {
        try {
            $updated = $action->execute(auth()->user(), $post);

            return response()->json([
                'message'   => $updated->is_pinned ? 'Post pinned.' : 'Post unpinned.',
                'is_pinned' => $updated->is_pinned,
            ]);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Api\PostController@togglePin failed', ['error' => $e->getMessage(), 'post_id' => $post->id]);
            return response()->json(['message' => 'Failed to toggle pin.'], 500);
        }
    }
}
