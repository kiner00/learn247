<?php

namespace App\Console\Commands;

use App\Events\CartAbandoned;
use App\Models\CartEvent;
use Illuminate\Console\Command;

class DetectAbandonedCarts extends Command
{
    protected $signature = 'carts:detect-abandoned {--hours=1 : Hours after checkout to consider abandoned}';

    protected $description = 'Detect abandoned carts and fire CartAbandoned events';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        // Find checkout_started events that have no matching payment_completed
        // and were created more than X hours ago
        $abandonedCarts = CartEvent::where('event_type', CartEvent::TYPE_CHECKOUT_STARTED)
            ->where('abandoned_email_sent', false)
            ->where('created_at', '<=', now()->subHours($hours))
            ->whereNotExists(function ($query) {
                $query->selectRaw(1)
                    ->from('cart_events as completed')
                    ->whereColumn('completed.community_id', 'cart_events.community_id')
                    ->whereColumn('completed.user_id', 'cart_events.user_id')
                    ->where('completed.event_type', CartEvent::TYPE_PAYMENT_COMPLETED)
                    ->whereColumn('completed.created_at', '>=', 'cart_events.created_at');
            })
            ->limit(100)
            ->get();

        if ($abandonedCarts->isEmpty()) {
            $this->info('No abandoned carts found.');

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($abandonedCarts as $cartEvent) {
            // Mark as abandoned and dispatched
            $cartEvent->update([
                'event_type'          => CartEvent::TYPE_ABANDONED,
                'abandoned_email_sent' => true,
            ]);

            CartAbandoned::dispatch($cartEvent);
            $count++;
        }

        $this->info("Detected {$count} abandoned cart(s).");

        return self::SUCCESS;
    }
}
