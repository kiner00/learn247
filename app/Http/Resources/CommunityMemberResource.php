<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunityMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'role' => $this->role,
            'joined_at' => $this->joined_at,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
