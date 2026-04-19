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
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'category' => $this->category,
            'avatar' => $this->avatar,
            'cover_image' => $this->cover_image,
            // Backward-compat: array of image URLs (image-type items only) for older mobile clients.
            'gallery_images' => collect($this->gallery_images ?? [])
                ->where('type', 'image')
                ->pluck('url')
                ->filter()
                ->values()
                ->all(),
            // New shape: full item objects with type/url/poster/hls_url/transcode status.
            'gallery_items' => $this->gallery_images ?? [],
            'is_private' => $this->is_private,
            'price' => $this->price,
            'currency' => $this->currency,
            'affiliate_commission_rate' => $this->affiliate_commission_rate ?? 0,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'members_count' => $this->when($this->members_count !== null, $this->members_count),
            // Membership flags — set dynamically by controllers when available
            'is_member' => $this->is_member ?? false,
            'is_owner' => $userId ? $this->owner_id === $userId : ($this->is_owner ?? false),
            'is_admin' => $this->is_admin ?? false,
            'created_at' => $this->created_at,
        ];
    }
}
