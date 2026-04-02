<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityLevelPerk;
use App\Services\Community\PlanLimitService;
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

    public function dangerZone(Community $community): Response
    {
        return Inertia::render('Communities/Settings/DangerZone', $this->baseProps($community));
    }
}
