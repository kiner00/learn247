<?php

namespace App\Http\Controllers\Web;

use App\Actions\Feed\CreatePost;
use App\Actions\Feed\DeletePost;
use App\Actions\Feed\TogglePin;
use App\Actions\Feed\UpdatePost;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Community;
use App\Models\Post;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    public function store(CreatePostRequest $request, Community $community, CreatePost $action): RedirectResponse
    {
        try {
            $action->execute($request->user(), $community, $request->validated());

            return back();
        } catch (\Throwable $e) {
            Log::error('PostController@store failed', ['error' => $e->getMessage(), 'community_id' => $community->id]);

            return back()->with('error', 'Failed to create post.');
        }
    }

    public function update(UpdatePostRequest $request, Post $post, UpdatePost $action): RedirectResponse
    {
        try {
            $action->execute($request->user(), $post, $request->validated());

            return back();
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('PostController@update failed', ['error' => $e->getMessage(), 'post_id' => $post->id]);

            return back()->with('error', 'Failed to update post.');
        }
    }

    public function destroy(Post $post, DeletePost $action): RedirectResponse
    {
        try {
            $action->execute(auth()->user(), $post);

            return back();
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('PostController@destroy failed', ['error' => $e->getMessage(), 'post_id' => $post->id]);

            return back()->with('error', 'Failed to delete post.');
        }
    }

    public function togglePin(Post $post, TogglePin $action): RedirectResponse
    {
        try {
            $action->execute(auth()->user(), $post);

            return back();
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('PostController@togglePin failed', ['error' => $e->getMessage(), 'post_id' => $post->id]);

            return back()->with('error', 'Failed to toggle pin.');
        }
    }
}
