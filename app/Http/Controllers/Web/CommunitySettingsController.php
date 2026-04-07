<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChatbotMessage;
use App\Models\Community;
use App\Models\CommunityLevelPerk;
use App\Models\Tag;
use App\Services\Community\PlanLimitService;
use App\Services\Email\EmailProviderFactory;
use Inertia\Inertia;
use Inertia\Response;

class CommunitySettingsController extends Controller
{
    private function baseProps(Community $community): array
    {
        $this->authorize('update', $community);

        return [
            'community'          => $community,
            'isPro'              => auth()->user()->creatorPlan() === 'pro',
            'canUseIntegrations' => app(PlanLimitService::class)->canSendAnnouncement(auth()->user()),
        ];
    }

    public function general(Community $community, PlanLimitService $planLimit): Response
    {
        return Inertia::render('Communities/Settings/General', array_merge(
            $this->baseProps($community),
            ['pricingGate' => $planLimit->pricingGate($community)]
        ));
    }

    public function affiliate(Community $community): Response
    {
        return Inertia::render('Communities/Settings/Affiliate', $this->baseProps($community));
    }

    public function aiTools(Community $community): Response
    {
        return Inertia::render('Communities/Settings/AiTools', $this->baseProps($community));
    }

    public function announcements(Community $community): Response
    {
        return Inertia::render('Communities/Settings/Announcements', $this->baseProps($community));
    }

    public function levelPerks(Community $community): Response
    {
        $perks = CommunityLevelPerk::where('community_id', $community->id)
            ->pluck('description', 'level')
            ->toArray();

        return Inertia::render('Communities/Settings/LevelPerks', array_merge(
            $this->baseProps($community),
            ['levelPerks' => $perks]
        ));
    }

    public function inviteMembers(Community $community): Response
    {
        return Inertia::render('Communities/Settings/InviteMembers', $this->baseProps($community));
    }

    public function integrations(Community $community): Response
    {
        return Inertia::render('Communities/Settings/Integrations', $this->baseProps($community));
    }

    public function domain(Community $community): Response
    {
        $appHost    = parse_url(config('app.url'), PHP_URL_HOST) ?? 'curzzo.com';
        $baseDomain = explode(':', $appHost)[0];

        return Inertia::render('Communities/Settings/Domain', array_merge(
            $this->baseProps($community),
            [
                'baseDomain' => $baseDomain,
                'serverIp'   => config('app.server_ip', ''),
            ]
        ));
    }

    public function sms(Community $community): Response
    {
        return Inertia::render('Communities/Settings/Sms', $this->baseProps($community));
    }

    public function email(Community $community): Response
    {
        return Inertia::render('Communities/Settings/Email', array_merge(
            $this->baseProps($community),
            [
                'hasApiKey'         => (bool) $community->resend_api_key,
                'emailProvider'     => $community->email_provider ?? '',
                'fromEmail'         => $community->resend_from_email,
                'fromName'          => $community->resend_from_name,
                'replyTo'           => $community->resend_reply_to,
                'domainId'          => $community->resend_domain_id,
                'domainStatus'      => $community->resend_domain_status,
                'providers'         => EmailProviderFactory::all(),
            ]
        ));
    }

    public function tags(Community $community): Response
    {
        $tags = Tag::where('community_id', $community->id)
            ->withCount('members')
            ->orderBy('name')
            ->get();

        return Inertia::render('Communities/Settings/Tags', array_merge(
            $this->baseProps($community),
            ['tags' => $tags]
        ));
    }

    public function workflows(Community $community): Response
    {
        return Inertia::render('Communities/Settings/Workflows', $this->baseProps($community));
    }

    public function dangerZone(Community $community): Response
    {
        return Inertia::render('Communities/Settings/DangerZone', $this->baseProps($community));
    }

    public function chatHistory(Community $community): Response
    {
        $this->authorize('update', $community);
        $community->load('owner:id,name,avatar');

        // Get unique users who chatted, with their latest message time
        $users = ChatbotMessage::where('community_id', $community->id)
            ->selectRaw('user_id, MAX(created_at) as last_chat_at, COUNT(*) as message_count')
            ->groupBy('user_id')
            ->orderByDesc('last_chat_at')
            ->with('user:id,name,avatar')
            ->get()
            ->map(fn ($row) => [
                'id'            => $row->user_id,
                'name'          => $row->user->name,
                'avatar'        => $row->user->avatar,
                'last_chat_at'  => $row->last_chat_at,
                'message_count' => $row->message_count,
            ]);

        return Inertia::render('Communities/Settings/ChatHistory', array_merge(
            $this->baseProps($community),
            ['chatUsers' => $users]
        ));
    }

    public function chatHistoryUser(Community $community, int $userId): Response
    {
        $this->authorize('update', $community);
        $community->load('owner:id,name,avatar');

        $messages = ChatbotMessage::where('community_id', $community->id)
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->select('id', 'role', 'content', 'conversation_id', 'created_at')
            ->get();

        $user = \App\Models\User::select('id', 'name', 'avatar')->findOrFail($userId);

        // Get user list for sidebar
        $users = ChatbotMessage::where('community_id', $community->id)
            ->selectRaw('user_id, MAX(created_at) as last_chat_at, COUNT(*) as message_count')
            ->groupBy('user_id')
            ->orderByDesc('last_chat_at')
            ->with('user:id,name,avatar')
            ->get()
            ->map(fn ($row) => [
                'id'            => $row->user_id,
                'name'          => $row->user->name,
                'avatar'        => $row->user->avatar,
                'last_chat_at'  => $row->last_chat_at,
                'message_count' => $row->message_count,
            ]);

        return Inertia::render('Communities/Settings/ChatHistory', array_merge(
            $this->baseProps($community),
            [
                'chatUsers'      => $users,
                'selectedUser'   => $user,
                'chatMessages'   => $messages,
            ]
        ));
    }
}
