<?php

namespace App\Http\Middleware;

use App\Models\Community;
use App\Models\CreatorSubscription;
use App\Models\DirectMessage;
use App\Models\Notification;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    private function unreadMessageCount(int $userId): int
    {
        return (int) DB::table('messages')
            ->join('community_members', function ($join) use ($userId) {
                $join->on('messages.community_id', '=', 'community_members.community_id')
                     ->where('community_members.user_id', '=', $userId);
            })
            ->where('messages.user_id', '!=', $userId)
            ->where(function ($q) {
                $q->whereNull('community_members.messages_last_read_at')
                  ->orWhereColumn('messages.created_at', '>', 'community_members.messages_last_read_at');
            })
            ->count();
    }

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * When the request arrives on a custom domain, the ResolveCustomDomain
     * middleware rewrites the URI to /communities/{slug}/…  We strip that
     * prefix from the Inertia response URL so the browser address bar stays clean.
     */
    public function urlResolver()
    {
        $dc = request()->attributes->get('domain_community');

        if (! $dc) {
            return null;
        }

        $prefix = '/communities/' . $dc->slug;

        return function () use ($prefix) {
            $uri = request()->getRequestUri();

            if (str_starts_with($uri, $prefix)) {
                $clean = substr($uri, strlen($prefix));
                return $clean === '' || $clean === false ? '/' : $clean;
            }

            return $uri;
        };
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id'             => $request->user()->id,
                    'name'           => $request->user()->name,
                    'email'          => $request->user()->email,
                    'avatar'         => $request->user()->avatar,
                    'is_super_admin' => $request->user()->is_super_admin,
                    'is_creator'     => $request->user()->is_super_admin || Community::where('owner_id', $request->user()->id)->where('price', '>', 0)->exists(),
                    'creator_plan'   => $request->user()->creatorPlan(),
                    'kyc_verified'   => $request->user()->isKycVerified(),
                    'kyc_status'     => $request->user()->kyc_status ?? 'none',
                    'theme'          => $request->user()->theme ?? 'light',
                    'cxp_balance'    => 0, // placeholder for future CXP token system
                ] : null,
                'communities' => $request->user()
                    ? $request->user()->communityMemberships()
                        ->with('community:id,name,slug,avatar')
                        ->whereHas('community')
                        ->get()
                        ->filter(fn ($m) => $m->community !== null)
                        ->map(fn ($m) => [
                            'id'     => $m->community->id,
                            'name'   => $m->community->name,
                            'slug'   => $m->community->slug,
                            'avatar' => $m->community->avatar,
                        ])
                        ->values()
                    : [],
            ],
            'flash' => [
                'success'          => $request->session()->get('success'),
                'error'            => $request->session()->get('error'),
                'quiz_result'      => $request->session()->get('quiz_result'),
                'show_ai_greeting' => $request->session()->pull('show_ai_greeting', false),
            ],
            'unread_messages'       => $request->user() ? $this->unreadMessageCount($request->user()->id) : 0,
            'unread_dms'            => $request->user()
                ? DirectMessage::where('receiver_id', $request->user()->id)->whereNull('read_at')->count()
                : 0,
            'unread_notifications'  => $request->user()
                ? Notification::where('user_id', $request->user()->id)->whereNull('read_at')->count()
                : 0,
            'app_theme' => Setting::get('app_theme', 'green'),
            's3_base_url' => rtrim(config('filesystems.disks.s3.url') ?: '', '/'),
            // Set when request comes in on a custom subdomain or custom domain
            'domain_community' => ($dc = $request->attributes->get('domain_community'))
                ? ['id' => $dc->id, 'slug' => $dc->slug]
                : null,
        ];
    }
}
