<?php

namespace App\Console\Commands;

use App\Models\EmailDailyStat;
use App\Models\EmailSend;
use App\Models\EmailUnsubscribe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AggregateEmailStats extends Command
{
    protected $signature = 'email-stats:aggregate {--date= : Date to aggregate (YYYY-MM-DD), defaults to yesterday}';

    protected $description = 'Aggregate email send data into daily stats';

    public function handle(): int
    {
        $date = $this->option('date')
            ? \Carbon\Carbon::parse($this->option('date'))->toDateString()
            : now()->subDay()->toDateString();

        $this->info("Aggregating email stats for {$date}...");

        // Aggregate sends by community
        $stats = EmailSend::whereDate('created_at', $date)
            ->select(
                'community_id',
                DB::raw('COUNT(*) as sent'),
                DB::raw("SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered"),
                DB::raw('SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened'),
                DB::raw('SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked'),
                DB::raw("SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced"),
                DB::raw("SUM(CASE WHEN status = 'complained' THEN 1 ELSE 0 END) as complained"),
            )
            ->groupBy('community_id')
            ->get();

        // Count unsubscribes for that date
        $unsubCounts = EmailUnsubscribe::whereDate('unsubscribed_at', $date)
            ->select('community_id', DB::raw('COUNT(*) as total'))
            ->groupBy('community_id')
            ->pluck('total', 'community_id');

        $count = 0;

        foreach ($stats as $row) {
            EmailDailyStat::updateOrCreate(
                [
                    'community_id' => $row->community_id,
                    'date' => $date,
                ],
                [
                    'sent' => $row->sent,
                    'delivered' => $row->delivered,
                    'opened' => $row->opened,
                    'clicked' => $row->clicked,
                    'bounced' => $row->bounced,
                    'complained' => $row->complained,
                    'unsubscribed' => $unsubCounts[$row->community_id] ?? 0,
                ]
            );
            $count++;
        }

        $this->info("Aggregated stats for {$count} communities.");

        return self::SUCCESS;
    }
}
