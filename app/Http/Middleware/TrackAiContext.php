<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

class TrackAiContext
{
    public function handle(Request $request, Closure $next)
    {
        $community = $request->route('community');
        if (is_object($community) && isset($community->id)) {
            Context::add('ai.community_id', (int) $community->id);
        } elseif (is_numeric($community)) {
            Context::add('ai.community_id', (int) $community);
        }

        if ($user = $request->user()) {
            Context::add('ai.user_id', $user->id);
        }

        return $next($request);
    }
}
