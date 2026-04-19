<?php

namespace App\Http\Middleware;

use App\Models\Community;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCustomDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $appUrl = config('app.url', '');
        $appHost = parse_url($appUrl, PHP_URL_HOST) ?? '';
        // Strip port (e.g. localhost:8080 → localhost)
        $bareHost = explode(':', $appHost)[0];

        if (! $bareHost || $host === $bareHost) {
            return $next($request);
        }

        $community = null;

        // ── Subdomain: test.curzzo.com ─────────────────────────────────────────
        if (str_ends_with($host, '.'.$bareHost)) {
            $sub = substr($host, 0, -strlen('.'.$bareHost));
            // Only single-level subdomains (no nested dots)
            if ($sub && ! str_contains($sub, '.')) {
                $community = Community::where('subdomain', $sub)->first();
            }
        }
        // ── Fully custom domain: myclassroom.com (Pro feature) ─────────────────
        else {
            $community = Community::where('custom_domain', $host)->first();
        }

        if (! $community) {
            return $next($request);
        }

        // Store for controllers / Inertia sharing
        $request->attributes->set('domain_community', $community);

        // Rewrite the URI so existing /communities/{slug}/... routes handle it.
        // Skip rewriting if Inertia's client-side router already sent the full path.
        // Skip auth routes — they are defined at root level, not under /communities/{slug}.
        $path = $request->getPathInfo();
        $prefix = '/communities/'.$community->slug;

        $authPaths = [
            '/login', '/register', '/logout',
            '/forgot-password', '/reset-password',
            '/set-password', '/email/verify',
            '/certificates',
        ];

        $isAuthPath = false;
        foreach ($authPaths as $authPath) {
            if ($path === $authPath || str_starts_with($path, $authPath.'/')) {
                $isAuthPath = true;
                break;
            }
        }

        if (! $isAuthPath && ! str_starts_with($path, $prefix)) {
            // Preserve the original URI so Inertia can return the clean custom-domain URL
            $request->attributes->set('original_uri', $request->getRequestUri());

            $newUri = $prefix.($path === '/' ? '' : $path);
            if ($qs = $request->getQueryString()) {
                $newUri .= '?'.$qs;
            }

            $request->initialize(
                $request->query->all(),
                $request->request->all(),
                $request->attributes->all(),
                $request->cookies->all(),
                $request->files->all(),
                array_replace($request->server->all(), ['REQUEST_URI' => $newUri]),
                $request->getContent()
            );
        }

        return $next($request);
    }
}
