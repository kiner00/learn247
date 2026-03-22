<?php

namespace App\Queries\Community;

use App\Models\Community;
use Illuminate\Support\Collection;

class GetFeaturedCommunities
{
    public function execute(): Collection
    {
        return Community::where('is_featured', true)
            ->with('owner:id,name')
            ->withCount('members')
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'id'            => $c->id,
                'name'          => $c->name,
                'slug'          => $c->slug,
                'description'   => $c->description,
                'cover_image'   => $c->cover_image,
                'avatar'        => $c->avatar,
                'price'         => (float) $c->price,
                'billing_type'  => $c->billing_type,
                'category'      => $c->category,
                'members_count' => $c->members_count,
                'owner'         => ['name' => $c->owner?->name],
            ]);
    }
}
