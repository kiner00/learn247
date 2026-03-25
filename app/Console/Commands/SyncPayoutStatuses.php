<?php

namespace App\Console\Commands;

use App\Actions\Affiliate\MarkAffiliateConversionPaid;
use App\Models\AffiliateConversion;
use App\Models\PayoutRequest;
use App\Services\XenditService;
use Illuminate\Console\Command;

class SyncPayoutStatuses extends Command
{
    protected $signature   = 'payouts:sync';
    protected $description = 'Sync approved payout requests with their actual Xendit disbursement status';

    public function handle(XenditService $xendit): int
    {
        $requests = PayoutRequest::where('status', PayoutRequest::STATUS_APPROVED)
            ->whereNotNull('xendit_reference')
            ->get();

        if ($requests->isEmpty()) {
            $this->info('No approved payout requests to sync.');
            return self::SUCCESS;
        }

        $this->info("Syncing {$requests->count()} approved payout request(s)...");

        foreach ($requests as $request) {
            try {
                $payout = $xendit->getPayout($request->xendit_reference);
                $status = strtolower($payout['status'] ?? '');

                if (in_array($status, ['succeeded', 'completed'])) {
                    if ($request->type === PayoutRequest::TYPE_AFFILIATE) {
                        $mark      = app(MarkAffiliateConversionPaid::class);
                        $remaining = (float) $request->amount;

                        AffiliateConversion::where('affiliate_id', $request->affiliate_id)
                            ->where('status', AffiliateConversion::STATUS_PENDING)
                            ->orderBy('created_at')
                            ->get()
                            ->each(function ($conversion) use (&$remaining, $mark) {
                                if ($remaining <= 0) return false;
                                $mark->execute($conversion);
                                $remaining -= (float) $conversion->commission_amount;
                            });
                    }

                    $request->update(['status' => PayoutRequest::STATUS_PAID]);
                    $this->line("  ✓ Request #{$request->id} ({$request->type}) → paid");
                } elseif (in_array($status, ['failed', 'cancelled', 'reversed'])) {
                    $request->update(['status' => PayoutRequest::STATUS_PENDING]);
                    $this->line("  ✗ Request #{$request->id} ({$request->type}) → reverted to pending (Xendit: {$status})");
                } else {
                    $this->line("  ~ Request #{$request->id} ({$request->type}) still {$status}, skipping");
                }
            } catch (\Exception $e) {
                $this->warn("  ! Request #{$request->id}: {$e->getMessage()}");
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
