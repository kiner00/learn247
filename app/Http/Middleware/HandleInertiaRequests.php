<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
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
    public function version(Request $request): ?string
    {
        return parent::version($request);
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
                    'is_super_admin' => $request->user()->is_super_admin,
                    'theme'          => $request->user()->theme ?? 'light',
                ] : null,
                'communities' => $request->user()
                    ? $request->user()->communityMemberships()
                        ->with('community:id,name,slug,avatar')
                        ->get()
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
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
        ];
    }
}
