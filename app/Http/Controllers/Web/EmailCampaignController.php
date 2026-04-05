<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailBroadcast;
use App\Models\Community;
use App\Models\EmailBroadcast;
use App\Models\EmailCampaign;
use App\Models\Tag;
use App\Services\Community\PlanLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailCampaignController extends Controller
{
    public function index(Community $community): Response
    {
        $this->authorize('update', $community);

        $campaigns = EmailCampaign::where('community_id', $community->id)
            ->withCount('broadcasts')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($campaign) {
                $latestBroadcast = $campaign->broadcasts()->latest()->first();

                return [
                    'id'              => $campaign->id,
                    'name'            => $campaign->name,
                    'type'            => $campaign->type,
                    'status'          => $campaign->status,
                    'broadcasts_count' => $campaign->broadcasts_count,
                    'total_sent'      => $campaign->broadcasts()->sum('total_sent'),
                    'latest_sent_at'  => $latestBroadcast?->sent_at,
                    'created_at'      => $campaign->created_at,
                ];
            });

        return Inertia::render('Communities/Email/Index', [
            'community'    => $community,
            'campaigns'    => $campaigns,
            'hasResendKey' => (bool) $community->resend_api_key,
        ]);
    }

    public function create(Community $community): Response
    {
        $this->authorize('update', $community);

        $tags = Tag::where('community_id', $community->id)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        return Inertia::render('Communities/Email/Create', [
            'community' => $community,
            'tags'      => $tags,
        ]);
    }

    public function store(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        if (! $community->resend_api_key) {
            return back()->withErrors(['resend' => 'Please configure your Resend API key first.']);
        }

        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'subject'                 => ['required', 'string', 'max:255'],
            'html_body'               => ['required', 'string', 'max:100000'],
            'reply_to'                => ['nullable', 'email', 'max:255'],
            'filter_tags'             => ['nullable', 'array'],
            'filter_tags.*'           => ['integer'],
            'filter_membership_type'  => ['nullable', 'string', 'in:free,paid'],
            'scheduled_at'            => ['nullable', 'date', 'after:now'],
        ]);

        $campaign = EmailCampaign::create([
            'community_id' => $community->id,
            'name'         => $data['name'],
            'type'         => EmailCampaign::TYPE_BROADCAST,
            'status'       => EmailCampaign::STATUS_DRAFT,
        ]);

        EmailBroadcast::create([
            'campaign_id'            => $campaign->id,
            'community_id'           => $community->id,
            'subject'                => $data['subject'],
            'html_body'              => $data['html_body'],
            'from_email'             => $community->resend_from_email,
            'from_name'              => $community->resend_from_name ?? $community->name,
            'reply_to'               => $data['reply_to'],
            'filter_tags'            => $data['filter_tags'] ?? null,
            'filter_membership_type' => $data['filter_membership_type'] ?? null,
            'scheduled_at'           => $data['scheduled_at'] ?? null,
            'status'                 => ! empty($data['scheduled_at']) ? EmailBroadcast::STATUS_SCHEDULED : EmailBroadcast::STATUS_DRAFT,
        ]);

        return redirect()->route('communities.email-campaigns.show', [$community, $campaign])
            ->with('success', 'Campaign created.');
    }

    public function show(Community $community, EmailCampaign $campaign): Response
    {
        $this->authorize('update', $community);
        abort_unless($campaign->community_id === $community->id, 404);

        $broadcast = $campaign->broadcasts()->latest()->first();

        $stats = $broadcast ? [
            'total_recipients' => $broadcast->total_recipients,
            'total_sent'       => $broadcast->total_sent,
            'total_failed'     => $broadcast->total_failed,
            'delivered'        => $broadcast->sends()->where('status', 'delivered')->count(),
            'opened'           => $broadcast->sends()->whereNotNull('opened_at')->count(),
            'clicked'          => $broadcast->sends()->whereNotNull('clicked_at')->count(),
            'bounced'          => $broadcast->sends()->where('status', 'bounced')->count(),
        ] : null;

        return Inertia::render('Communities/Email/Show', [
            'community' => $community,
            'campaign'  => [
                'id'         => $campaign->id,
                'name'       => $campaign->name,
                'status'     => $campaign->status,
                'created_at' => $campaign->created_at,
            ],
            'broadcast' => $broadcast ? [
                'id'                     => $broadcast->id,
                'subject'                => $broadcast->subject,
                'html_body'              => $broadcast->html_body,
                'status'                 => $broadcast->status,
                'sent_at'                => $broadcast->sent_at,
                'scheduled_at'           => $broadcast->scheduled_at,
                'filter_tags'            => $broadcast->filter_tags,
                'filter_membership_type' => $broadcast->filter_membership_type,
            ] : null,
            'stats' => $stats,
        ]);
    }

    public function update(Request $request, Community $community, EmailCampaign $campaign): RedirectResponse
    {
        $this->authorize('update', $community);
        abort_unless($campaign->community_id === $community->id, 404);

        if (in_array($campaign->status, [EmailCampaign::STATUS_SENT, EmailCampaign::STATUS_SENDING])) {
            return back()->withErrors(['campaign' => 'Cannot edit a sent or sending campaign.']);
        }

        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'subject'                 => ['required', 'string', 'max:255'],
            'html_body'               => ['required', 'string', 'max:100000'],
            'reply_to'                => ['nullable', 'email', 'max:255'],
            'filter_tags'             => ['nullable', 'array'],
            'filter_tags.*'           => ['integer'],
            'filter_membership_type'  => ['nullable', 'string', 'in:free,paid'],
            'scheduled_at'            => ['nullable', 'date', 'after:now'],
        ]);

        $campaign->update(['name' => $data['name']]);

        $broadcast = $campaign->broadcasts()->latest()->first();
        if ($broadcast) {
            $broadcast->update([
                'subject'                => $data['subject'],
                'html_body'              => $data['html_body'],
                'reply_to'               => $data['reply_to'],
                'filter_tags'            => $data['filter_tags'] ?? null,
                'filter_membership_type' => $data['filter_membership_type'] ?? null,
                'scheduled_at'           => $data['scheduled_at'] ?? null,
                'status'                 => ! empty($data['scheduled_at']) ? EmailBroadcast::STATUS_SCHEDULED : EmailBroadcast::STATUS_DRAFT,
            ]);
        }

        return back()->with('success', 'Campaign updated.');
    }

    public function send(Request $request, Community $community, EmailCampaign $campaign): RedirectResponse
    {
        $this->authorize('update', $community);
        abort_unless($campaign->community_id === $community->id, 404);

        if (! $community->resend_api_key) {
            return back()->withErrors(['resend' => 'Please configure your Resend API key first.']);
        }

        $broadcast = $campaign->broadcasts()->where('status', EmailBroadcast::STATUS_DRAFT)->latest()->first();

        if (! $broadcast) {
            return back()->withErrors(['campaign' => 'No draft broadcast to send.']);
        }

        $campaign->update(['status' => EmailCampaign::STATUS_SENDING]);

        SendEmailBroadcast::dispatch($broadcast);

        return back()->with('success', 'Campaign is being sent! Emails will be delivered shortly.');
    }

    public function destroy(Community $community, EmailCampaign $campaign): RedirectResponse
    {
        $this->authorize('update', $community);
        abort_unless($campaign->community_id === $community->id, 404);

        if ($campaign->status === EmailCampaign::STATUS_SENDING) {
            return back()->withErrors(['campaign' => 'Cannot delete a campaign that is currently sending.']);
        }

        $campaign->delete();

        return redirect()->route('communities.email-campaigns.index', $community)
            ->with('success', 'Campaign deleted.');
    }
}
