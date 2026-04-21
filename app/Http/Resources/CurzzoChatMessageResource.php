<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurzzoChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Accepts either a CurzzoMessage model or a normalized array shape
        // (id, role, content/text). Normalizes to the chat-friendly shape.
        if (is_array($this->resource)) {
            return [
                'id' => $this->resource['id'],
                'role' => $this->resource['role'],
                'text' => $this->resource['text'] ?? $this->resource['content'] ?? '',
            ];
        }

        return [
            'id' => $this->id,
            'role' => $this->role,
            'text' => $this->content,
        ];
    }
}
