<?php

namespace App\Console\Commands;

use App\Actions\Billing\CreateRenewalInvoice;
use App\Mail\SubscriptionRenewalReminder;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RenewExpiringSubscriptions extends Command
{
    protected $signature   = 'subscriptions:renew';
    protected $description = 'Send renewal invoices for subscriptions expiring within 3 days';

    public function handle(CreateRenewalInvoice $action): int
    {
        // Find active subscriptions expiring in 1–3 days (daily window avoids duplicates)
        $subscriptions = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->whereBetween('expires_at', [now()->addDay(), now()->addDays(3)])
            ->with(['user', 'community'])
            ->get();

        $this->info("Found {$subscriptions->count()} expiring subscription(s).");

        foreach ($subscriptions as $subscription) {
            try {
                $renewalUrl = $action->execute($subscription);

                Mail::to($subscription->user->email)
                    ->send(new SubscriptionRenewalReminder($subscription, $renewalUrl));

                $this->info("Renewal sent → user #{$subscription->user_id} ({$subscription->community->name})");
                Log::info('Renewal invoice created', ['subscription_id' => $subscription->id]);
            } catch (\Throwable $e) {
                $this->error("Failed for subscription #{$subscription->id}: {$e->getMessage()}");
                Log::error('Renewal failed', [
                    'subscription_id' => $subscription->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
