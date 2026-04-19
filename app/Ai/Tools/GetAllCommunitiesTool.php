<?php

namespace App\Ai\Tools;

use App\Models\Community;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetAllCommunitiesTool implements Tool
{
    public function description(): string
    {
        return 'Get all publicly available communities on the Curzzo platform. Use this when the user asks what communities exist, wants to explore, or is looking for one to join.';
    }

    public function handle(Request $request): string
    {
        $query = strtolower(trim($request->string('search', '')));

        $communities = Community::withoutTrashed()
            ->select('id', 'name', 'slug', 'description', 'category', 'price', 'billing_type', 'is_private')
            ->when($query, fn ($q) => $q->where(fn ($q2) => $q2
                ->whereRaw('LOWER(name) LIKE ?', ["%{$query}%"])
                ->orWhereRaw('LOWER(category) LIKE ?', ["%{$query}%"])
            ))
            ->orderBy('name')
            ->get();

        if ($communities->isEmpty()) {
            return 'No communities found'.($query ? " matching \"{$query}\"" : '').'.';
        }

        $result = $communities->map(fn ($c) => [
            'name' => $c->name,
            'slug' => $c->slug,
            'category' => $c->category,
            'description' => $c->description ? str($c->description)->limit(120)->value() : null,
            'price' => $c->price > 0 ? "₱{$c->price} ({$c->billing_type})" : 'Free',
            'is_private' => $c->is_private,
        ])->values()->toArray();

        return json_encode($result, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Optional keyword to filter communities by name or category.'),
        ];
    }
}
