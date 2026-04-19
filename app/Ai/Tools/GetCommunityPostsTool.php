<?php

namespace App\Ai\Tools;

use App\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetCommunityPostsTool implements Tool
{
    public function __construct(private int $communityId) {}

    public function description(): string
    {
        return 'Get recent posts from this community. Use this to answer questions about community discussions, announcements, and shared content.';
    }

    public function handle(Request $request): string
    {
        $query = trim($request->string('query', ''));

        $posts = Post::where('community_id', $this->communityId)
            ->when($query, fn ($q) => $q->where('title', 'LIKE', "%{$query}%")->orWhere('content', 'LIKE', "%{$query}%"))
            ->with('user:id,name')
            ->select('id', 'user_id', 'title', 'content', 'is_pinned', 'created_at')
            ->latest()
            ->limit(15)
            ->get();

        if ($posts->isEmpty()) {
            return $query ? "No posts found matching \"{$query}\"." : 'No posts in this community yet.';
        }

        $result = $posts->map(fn ($p) => [
            'title' => $p->title,
            'content' => \Illuminate\Support\Str::limit($p->content, 300),
            'author' => $p->user->name,
            'pinned' => $p->is_pinned,
            'posted_at' => $p->created_at->diffForHumans(),
        ])->values()->toArray();

        return json_encode($result, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Optional keyword to search posts by title or content.'),
        ];
    }
}
