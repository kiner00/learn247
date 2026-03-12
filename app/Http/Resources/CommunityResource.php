<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;

        return [
            'id'                         => $this->id,
            'name'                       => $this->name,
            'slug'                       => $this->slug,
            'description'                => $this->description,
            'category'                   => $this->category,
            'avatar'                     => $this->avatar,
            'cover_image'                => $this->cover_image,
            'gallery_images'             => $this->gallery_images ?? [],
            'is_private'                 => $this->is_private,
            'price'                      => $this->price,
            'currency'                   => $this->currency,
            'affiliate_commission_rate'  => $this->affiliate_commission_rate ?? 0,
            'owner'                      => new UserResource($this->whenLoaded('owner')),
            'members_count'              => $this->when($this->members_count !== null, $this->members_count),
            // Membership flags — set dynamically by controllers when available
            'is_member'                  => $this->is_member ?? false,
            'is_owner'                   => $userId ? $this->owner_id === $userId : ($this->is_owner ?? false),
            'is_admin'                   => $this->is_admin ?? false,
            'created_at'                 => $this->created_at,
        ];
    }
}
