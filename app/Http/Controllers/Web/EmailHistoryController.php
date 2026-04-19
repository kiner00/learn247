<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\EmailSend;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailHistoryController extends Controller
{
    public function index(Request $request, Community $community): Response
    {
        $this->authorize('update', $community);

        $filter = $request->get('status', '');
        $search = $request->get('search', '');
        $page = (int) $request->get('page', 1);

        $query = EmailSend::where('community_id', $community->id)
            ->with([
                'member.user:id,name,email,avatar',
                'broadcast:id,subject,campaign_id',
                'broadcast.campaign:id,name',
            ])
            ->orderByDesc('created_at');

        if ($filter) {
            $query->where('status', $filter);
        }

        if ($search) {
            $query->whereHas('member.user', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        $sends = $query->paginate(50, ['*'], 'page', $page);

        $items = $sends->through(fn ($send) => [
            'id' => $send->id,
            'member_name' => $send->member?->user?->name ?? '—',
            'member_email' => $send->member?->user?->email ?? '—',
            'member_avatar' => $send->member?->user?->avatar,
            'campaign_name' => $send->broadcast?->campaign?->name,
            'subject' => $send->broadcast?->subject ?? 'Sequence email',
            'status' => $send->status,
            'opened_at' => $send->opened_at,
            'clicked_at' => $send->clicked_at,
            'bounced_at' => $send->bounced_at,
            'failed_reason' => $send->failed_reason,
            'created_at' => $send->created_at,
        ]);

        // Summary counts
        $counts = EmailSend::where('community_id', $community->id)
            ->selectRaw("
                COUNT(*) as total,
                SUM(status = 'sent') as sent,
                SUM(status = 'delivered') as delivered,
                SUM(opened_at IS NOT NULL) as opened,
                SUM(clicked_at IS NOT NULL) as clicked,
                SUM(status = 'bounced') as bounced,
                SUM(status = 'failed') as failed
            ")
            ->first();

        return Inertia::render('Communities/Email/History', [
            'community' => $community,
            'sends' => $items,
            'counts' => [
                'total' => $counts->total ?? 0,
                'sent' => $counts->sent ?? 0,
                'delivered' => $counts->delivered ?? 0,
                'opened' => $counts->opened ?? 0,
                'clicked' => $counts->clicked ?? 0,
                'bounced' => $counts->bounced ?? 0,
                'failed' => $counts->failed ?? 0,
            ],
            'filter' => $filter,
            'search' => $search,
        ]);
    }
}
