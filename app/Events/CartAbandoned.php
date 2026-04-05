<?php

namespace App\Events;

use App\Models\CartEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartAbandoned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CartEvent $cartEvent,
    ) {}
}
