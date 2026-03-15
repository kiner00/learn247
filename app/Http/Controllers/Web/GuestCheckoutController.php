<?php

namespace App\Http\Controllers\Web;

use App\Actions\Auth\GuestCheckout;
use App\Actions\Billing\StartSubscriptionCheckout;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
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
        $user      = $checkout->findOrCreateUser($data);

        if ($checkout->hasActiveSubscription($user->id, $community->id)) {
            return back()->withErrors(['email' => 'This email already has an active subscription to this community.']);
        }

        $callbackUrl = URL::temporarySignedRoute(
            'checkout.callback',
            now()->addHours(2),
            ['user' => $user->id, 'community' => $community->slug],
        );

        $result = $action->execute($user, $community, $code, successRedirectUrl: $callbackUrl);

        return Inertia::location($result['checkout_url']);
    }
}
