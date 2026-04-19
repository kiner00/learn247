<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Queries\Admin\ListTrashedPosts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostModerationController extends Controller
{
    public function trashed(Request $request, ListTrashedPosts $query): Response
    {
        return Inertia::render('Admin/TrashedPosts', $query->execute(
            $request->string('search')->trim()->toString()
        ));
    }

    public function restore(int $postId): RedirectResponse
    {
        Post::onlyTrashed()->findOrFail($postId)->restore();

        return back()->with('success', 'Post restored.');
    }

    public function forceDelete(int $postId): RedirectResponse
    {
        Post::onlyTrashed()->findOrFail($postId)->forceDelete();

        return back()->with('success', 'Post permanently deleted.');
    }
}
