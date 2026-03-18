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

    public function update(Post $post): RedirectResponse
    {
        abort_unless(auth()->id() === $post->user_id, 403);

        $data = request()->validate([
            'title'   => 'nullable|string|max:255',
            'content' => 'required|string|max:10000',
        ]);

        $post->update($data);

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
