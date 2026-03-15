<?php

namespace App\Actions\Feed;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ToggleLike
{
    private const ALLOWED_TYPES = ['like', 'handshake', 'trophy'];

    /**
     * @return array{action: string, type: string, likes_count: int}
     */
    public function execute(User $user, Model $likeable, string $type = 'like'): array
    {
        $type = in_array($type, self::ALLOWED_TYPES) ? $type : 'like';

        /** @var MorphMany $likes */
        $likes    = $likeable->likes();
        $existing = $likes->where('user_id', $user->id)->first();

        if ($existing && $existing->type === $type) {
            $existing->delete();
            $action = 'removed';
        } elseif ($existing) {
            $existing->update(['type' => $type]);
            $action = 'updated';
        } else {
            $likes->create(['user_id' => $user->id, 'type' => $type]);
            $action = 'added';
        }

        return [
            'action'      => $action,
            'type'        => $type,
            'likes_count' => $likes->count(),
        ];
    }
}
