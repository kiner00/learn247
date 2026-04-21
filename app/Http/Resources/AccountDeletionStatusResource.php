<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class AccountDeletionStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $requestedAt = $this->deleted_at;
        $willBeDeletedAt = $requestedAt?->copy()->addDays(User::DELETION_GRACE_DAYS);
        $daysRemaining = $willBeDeletedAt?->isFuture()
            ? (int) ceil(now()->diffInHours($willBeDeletedAt, true) / 24)
            : 0;

        return [
            'requested' => $requestedAt !== null,
            'requested_at' => $requestedAt,
            'will_be_deleted_at' => $willBeDeletedAt,
            'days_remaining' => $daysRemaining,
            'can_cancel' => $requestedAt !== null && $daysRemaining > 0,
        ];
    }
}
