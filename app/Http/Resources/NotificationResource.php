<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'type'           => $this->type,
            'data'           => $this->data,
            'read_at'        => $this->read_at,
            'created_at'     => $this->created_at,
            'actor'          => $this->whenLoaded('actor', fn () => [
                'id'     => $this->actor->id,
                'name'   => $this->actor->name,
                'avatar' => $this->actor->avatar,
            ]),
            'community'      => $this->whenLoaded('community', fn () => [
                'id'   => $this->community->id,
                'name' => $this->community->name,
                'slug' => $this->community->slug,
            ]),
        ];
    }
}
