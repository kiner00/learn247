<?php

namespace App\Http\Middleware;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveMembership
{
    /**
     * Gate access to community routes.
     * - Free communities: check membership row exists.
     * - Paid communities: check active, non-expired subscription.
     * - Owner always passes through.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $community = $request->route('community');

        if (! $community instanceof Community) {
            $slug      = $community ?? $request->route('slug');
            $community = Community::where('slug', $slug)->first();
        }

        if (! $community) {
            abort(404);
        }

        $user = $request->user();

        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->route('login');
        }

        if ($user->id === $community->owner_id || $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($community->isFree()) {
            $isMember = CommunityMember::where('community_id', $community->id)
                ->where('user_id', $user->id)
                ->exists();

            if (! $isMember) {
                return $this->deny($request, $community, 'You must be a member of this community.');
            }

            return $next($request);
        }

        $hasActive = Subscription::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        if (! $hasActive) {
            return $this->deny($request, $community, 'An active membership is required.');
        }

        return $next($request);
    }

    private function deny(Request $request, Community $community, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('communities.about', $community->slug)->with('error', $message);
    }
}
