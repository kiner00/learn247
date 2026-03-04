<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'slug'           => $this->slug,
            'description'    => $this->description,
            'avatar'         => $this->avatar,
            'is_private'     => $this->is_private,
            'price'          => $this->price,
            'currency'       => $this->currency,
            'owner'          => new UserResource($this->whenLoaded('owner')),
            'members_count'  => $this->when($this->members_count !== null, $this->members_count),
            'created_at'     => $this->created_at,
        ];
    }
}
