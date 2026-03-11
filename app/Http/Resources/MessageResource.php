<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $myId = $request->user()?->id;

        return [
            'id'         => $this->id,
            'content'    => $this->content,
            'is_mine'    => $this->sender_id === $myId,
            'read_at'    => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
