<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'content'    => $this->content,
            'created_at' => $this->created_at,
            'user'       => $this->whenLoaded('user', fn () => [
                'id'       => $this->user->id,
                'name'     => $this->user->name,
                'username' => $this->user->username,
                'avatar'   => $this->user->avatar,
            ]),
        ];
    }
}
