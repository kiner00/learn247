<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\EmailDailyStat;
use App\Models\EmailSend;
use App\Models\EmailUnsubscribe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmailAnalyticsController extends Controller
{
    public function index(Request $request, Community $community): Response
    {
        $this->authorize('update', $community);

        $days = (int) $request->get('days', 30);
        $days = min(max($days, 7), 90);

        // Daily stats from aggregated table
        $dailyStats = EmailDailyStat::where('community_id', $community->id)
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('date')
            ->get()
            ->map(fn ($stat) => [
                'date' => $stat->date->format('M j'),
                'sent' => $stat->sent,
                'delivered' => $stat->delivered,
                'opened' => $stat->opened,
                'clicked' => $stat->clicked,
                'bounced' => $stat->bounced,
                'unsubscribed' => $stat->unsubscribed,
            ]);

        // Lifetime totals
        $totals = EmailSend::where('community_id', $community->id)
            ->select(
                DB::raw('COUNT(*) as total_sent'),
                DB::raw("SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as total_delivered"),
                DB::raw('SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as total_opened'),
                DB::raw('SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as total_clicked'),
                DB::raw("SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as total_bounced"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as total_failed"),
            )
            ->first();

        $totalUnsubscribed = EmailUnsubscribe::where('community_id', $community->id)->count();

        return Inertia::render('Communities/Email/Analytics', [
            'community' => $community,
            'dailyStats' => $dailyStats,
            'totals' => [
                'sent' => $totals->total_sent ?? 0,
                'delivered' => $totals->total_delivered ?? 0,
                'opened' => $totals->total_opened ?? 0,
                'clicked' => $totals->total_clicked ?? 0,
                'bounced' => $totals->total_bounced ?? 0,
                'failed' => $totals->total_failed ?? 0,
                'unsubscribed' => $totalUnsubscribed,
            ],
            'days' => $days,
        ]);
    }
}
