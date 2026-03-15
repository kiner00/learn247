<?php

namespace App\Http\Controllers\Web;

use App\Actions\Feed\CreatePost;
use App\Actions\Feed\DeletePost;
use App\Actions\Feed\TogglePin;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePostRequest;
use App\Models\Community;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;

class PostController extends Controller
{
    public function store(CreatePostRequest $request, Community $community, CreatePost $action): RedirectResponse
    {
        $action->execute($request->user(), $community, $request->validated());

        return back();
    }

    public function destroy(Post $post, DeletePost $action): RedirectResponse
    {
        $action->execute(auth()->user(), $post);

        return back();
    }

    public function togglePin(Post $post, TogglePin $action): RedirectResponse
    {
        $action->execute(auth()->user(), $post);

        return back();
    }
}
