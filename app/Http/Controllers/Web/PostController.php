<?php

namespace App\Http\Controllers\Web;

use App\Actions\Feed\CreatePost;
use App\Actions\Feed\DeletePost;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function store(Request $request, Community $community, CreatePost $action): RedirectResponse
    {
        $data = $request->validate([
            'title'   => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        $action->execute($request->user(), $community, $data);

        return back();
    }

    public function destroy(Post $post, DeletePost $action): RedirectResponse
    {
        $action->execute(auth()->user(), $post);

        return back();
    }
}
