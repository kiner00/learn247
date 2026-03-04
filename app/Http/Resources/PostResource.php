<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'community_id'   => $this->community_id,
            'title'          => $this->title,
            'content'        => $this->content,
            'is_pinned'      => $this->is_pinned,
            'author'         => new UserResource($this->whenLoaded('author')),
            'comments_count' => $this->when($this->comments_count !== null, $this->comments_count),
            'comments'       => CommentResource::collection($this->whenLoaded('comments')),
            'created_at'     => $this->created_at,
        ];
    }
}
