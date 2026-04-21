<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Subscription $resource
 */
class SubscriptionStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sub = $this->resource;

        return [
            'id' => $sub->id,
            'community_id' => $sub->community_id,
            'status' => $sub->status,
            'is_active' => $sub->isActive(),
            'expires_at' => $sub->expires_at?->toIso8601String(),
            'xendit_invoice_url' => $sub->xendit_invoice_url,
            'is_recurring' => $sub->isRecurring(),
            'recurring_status' => $sub->recurring_status,
        ];
    }
}
