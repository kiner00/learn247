<?php

namespace App\Http\Controllers\Web;

use App\Actions\Feed\CreatePost;
use App\Actions\Feed\DeletePost;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Notification;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function store(Request $request, Community $community, CreatePost $action): RedirectResponse
    {
        $data = $request->validate([
            'title'     => ['nullable', 'string', 'max:255'],
            'content'   => ['required', 'string'],
            'image'     => ['nullable', 'image', 'max:5120'],
            'video_url' => ['nullable', 'url', 'max:500'],
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('post-images', 'public');
            $data['image'] = asset('storage/' . $data['image']);
        }

        $post = $action->execute($request->user(), $community, $data);

        // Notify community owner about new post (if not self)
        if ($community->owner_id !== $request->user()->id) {
            Notification::create([
                'user_id'      => $community->owner_id,
                'actor_id'     => $request->user()->id,
                'community_id' => $community->id,
                'type'         => 'new_post',
                'data'         => [
                    'post_title' => $post->title ?? 'New post',
                    'message'    => "{$request->user()->name} posted in {$community->name}",
                ],
            ]);
        }

        return back();
    }

    public function destroy(Post $post, DeletePost $action): RedirectResponse
    {
        $action->execute(auth()->user(), $post);

        return back();
    }

    public function togglePin(Request $request, Post $post): RedirectResponse
    {
        $user = $request->user();
        $community = $post->community;

        // Only admins and the community owner can pin
        $membership = $community->members()->where('user_id', $user->id)->first();
        $isAdmin    = $community->owner_id === $user->id || $membership?->role === 'admin';

        abort_unless($isAdmin, 403);

        $post->update(['is_pinned' => ! $post->is_pinned]);

        return back();
    }
}
