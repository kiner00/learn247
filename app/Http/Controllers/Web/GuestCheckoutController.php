<?php

namespace App\Http\Controllers\Web;

use App\Actions\Auth\GuestCheckout;
use App\Actions\Billing\StartSubscriptionCheckout;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GuestCheckoutController extends Controller
{
    public function process(Request $request, string $code, StartSubscriptionCheckout $action, GuestCheckout $checkout): mixed
    {
        $affiliate = Affiliate::where('code', $code)
            ->where('status', Affiliate::STATUS_ACTIVE)
            ->with('community')
            ->first();

        if (! $affiliate) {
            return redirect()->route('communities.index');
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email'],
            'phone'      => ['required', 'string', 'max:30'],
        ]);

        $community = $affiliate->community;

        if ($community->isPendingDeletion()) {
            return back()->withErrors(['email' => 'This community is no longer accepting new members.']);
        }

        $user = $checkout->findOrCreateUser($data);

        if ($checkout->hasActiveSubscription($user->id, $community->id)) {
            return back()->withErrors(['email' => 'This email already has an active subscription to this community.']);
        }

        $callbackUrl = self::buildCallbackUrl($user->id, $community->slug);

        $result = $action->execute($user, $community, $code, successRedirectUrl: $callbackUrl);

        return Inertia::location($result['checkout_url']);
    }

    /**
     * Build an HMAC-verified callback URL that won't break when Xendit
     * appends extra query parameters after payment.
     */
    public static function buildCallbackUrl(int $userId, string $communitySlug): string
    {
        $expires = now()->addHours(2)->getTimestamp();
        $token   = hash_hmac('sha256', "{$userId}|{$communitySlug}|{$expires}", config('app.key'));

        return route('checkout.callback', [
            'user'      => $userId,
            'community' => $communitySlug,
            'expires'   => $expires,
            'token'     => $token,
        ]);
    }
}
