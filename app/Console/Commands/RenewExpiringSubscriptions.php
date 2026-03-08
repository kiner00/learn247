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
    protected $description = 'Send renewal reminders at 5 days and 1 day before expiry';

    public function handle(CreateRenewalInvoice $action): int
    {
        $this->sendReminders($action, days: 5, column: 'reminder_5d_sent_at', urgent: false);
        $this->sendReminders($action, days: 1, column: 'reminder_1d_sent_at', urgent: true);

        return self::SUCCESS;
    }

    private function sendReminders(CreateRenewalInvoice $action, int $days, string $column, bool $urgent): void
    {
        $subscriptions = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->whereBetween('expires_at', [now()->addDays($days - 1), now()->addDays($days + 1)])
            ->whereNull($column)
            ->with(['user', 'community'])
            ->get();

        $this->info("Found {$subscriptions->count()} subscription(s) for {$days}-day reminder.");

        foreach ($subscriptions as $subscription) {
            try {
                // Create a new invoice only for the first (5d) reminder.
                // The 1d reminder reuses the URL already stored from the 5d reminder so the
                // Xendit invoice ID on the subscription is never overwritten between reminders.
                $renewalUrl = $urgent
                    ? ($subscription->xendit_invoice_url ?? $action->execute($subscription))
                    : $action->execute($subscription);

                Mail::to($subscription->user->email)
                    ->send(new SubscriptionRenewalReminder($subscription, $renewalUrl, $urgent));

                $subscription->update([$column => now()]);

                $this->info("Reminder ({$days}d) sent → user #{$subscription->user_id} ({$subscription->community->name})");
                Log::info("Renewal {$days}d reminder sent", ['subscription_id' => $subscription->id]);
            } catch (\Throwable $e) {
                $this->error("Failed for subscription #{$subscription->id}: {$e->getMessage()}");
                Log::error("Renewal {$days}d reminder failed", [
                    'subscription_id' => $subscription->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }
    }
}
