<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Resolve custom/subdomain before anything else so route matching sees the rewritten URI
        $middleware->prepend(\App\Http\Middleware\ResolveCustomDomain::class);

        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        // Inertia: encrypt sessions and share flash data
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Rate limit all API routes: 120 requests/min per user
        $middleware->api(append: [
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':120,1',
        ]);

        $middleware->alias([
            'active.member' => \App\Http\Middleware\EnsureActiveMembership::class,
        ]);

        // Disable rate limiting in local environment (for k6 / stress testing)
        if (env('APP_ENV') === 'local') {
            $middleware->api(remove: [
                \Illuminate\Routing\Middleware\ThrottleRequests::class,
                \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
            ]);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        \Sentry\Laravel\Integration::handles($exceptions);

        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, Throwable $e, \Illuminate\Http\Request $request) {
            $status = $response->getStatusCode();

            if (! app()->environment('local') && in_array($status, [500, 503, 404, 403, 419])) {
                $titles = [
                    500 => 'Something went wrong',
                    503 => 'Under maintenance',
                    404 => 'Page not found',
                    403 => 'Access denied',
                    419 => 'Session expired',
                ];

                return Inertia::render('Error', [
                    'status' => $status,
                    'title' => $titles[$status] ?? 'Error',
                ])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
