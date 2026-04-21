<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurzzoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'name' => $this->name,
            'description' => $this->description,
            'avatar' => $this->avatar,
            'cover_image' => $this->cover_image,
            'preview_video' => $this->preview_video,
            'preview_video_sound' => (bool) $this->preview_video_sound,
            'access_type' => $this->access_type,
            'instructions' => $this->when(
                (bool) $request->user()?->can('update', $this->community),
                $this->instructions,
            ),
            'personality' => $this->personality,
            'model_tier' => $this->model_tier,
            'price' => $this->price === null ? null : (float) $this->price,
            'currency' => $this->currency,
            'billing_type' => $this->billing_type,
            'affiliate_commission_rate' => $this->affiliate_commission_rate,
            'is_free' => $this->isFree(),
            'is_active' => (bool) $this->is_active,
            'position' => $this->position,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
