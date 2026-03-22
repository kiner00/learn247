<?php

namespace App\Queries\Admin;

use App\Models\Post;

class ListTrashedPosts
{
    public function execute(string $search): array
    {
        $posts = Post::onlyTrashed()
            ->with(['author:id,name', 'community:id,name,slug'])
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            }))
            ->latest('deleted_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($p) => [
                'id'             => $p->id,
                'title'          => $p->title,
                'content'        => substr($p->content, 0, 120),
                'author'         => $p->author?->name,
                'community'      => $p->community?->name,
                'community_slug' => $p->community?->slug,
                'deleted_at'     => $p->deleted_at?->toDateString(),
            ]);

        return [
            'posts'   => $posts,
            'filters' => ['search' => $search],
        ];
    }
}
