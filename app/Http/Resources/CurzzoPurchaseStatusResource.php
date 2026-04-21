<?php

namespace App\Http\Resources;

use App\Models\CurzzoPurchase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CurzzoPurchase $resource
 */
class CurzzoPurchaseStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $purchase = $this->resource;

        return [
            'id' => $purchase->id,
            'curzzo_id' => $purchase->curzzo_id,
            'status' => $purchase->status,
            'is_paid' => $purchase->status === CurzzoPurchase::STATUS_PAID,
            'paid_at' => $purchase->paid_at?->toIso8601String(),
            'expires_at' => $purchase->expires_at?->toIso8601String(),
            'is_recurring' => $purchase->isRecurring(),
            'recurring_status' => $purchase->recurring_status,
        ];
    }
}
