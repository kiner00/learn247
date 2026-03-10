<?php

namespace App\Http\Controllers\Web;

use App\Actions\Billing\StartSubscriptionCheckout;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GuestCheckoutController extends Controller
{
    public function show(string $code): mixed
    {
        $affiliate = Affiliate::where('code', $code)
            ->where('status', Affiliate::STATUS_ACTIVE)
            ->with('community')
            ->first();

        if (! $affiliate) {
            return redirect()->route('communities.index');
        }

        return Inertia::render('GuestCheckout', [
            'community' => [
                'name'     => $affiliate->community->name,
                'slug'     => $affiliate->community->slug,
                'price'    => $affiliate->community->price,
                'currency' => $affiliate->community->currency,
                'cover'    => $affiliate->community->cover,
            ],
            'refCode' => $code,
        ]);
    }

    public function process(Request $request, string $code, StartSubscriptionCheckout $action): mixed
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
        ]);

        $community = $affiliate->community;

        // Find existing user or create a guest account
        $user = User::where('email', $data['email'])->first();
        $isNewUser = false;

        if (! $user) {
            $isNewUser = true;
            $user = User::create([
                'name'                 => trim($data['first_name'] . ' ' . $data['last_name']),
                'email'                => $data['email'],
                'password'             => Hash::make(Str::random(32)),
                'needs_password_setup' => true,
            ]);
            $user->update(['username' => $this->generateUsername($data['first_name'], $data['last_name'], $user->id)]);
        }

        // Check if already subscribed
        $existing = Subscription::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->first();

        if ($existing) {
            return back()->withErrors(['email' => 'This email already has an active subscription to this community.']);
        }

        $result = $action->execute(
            $user,
            $community,
            $code,
            successRedirectUrl: config('app.url') . '/checkout-success',
        );

        return Inertia::location($result['checkout_url']);
    }

    private function generateUsername(string $firstName, string $lastName, int $userId): string
    {
        $slug = function (string $s): string {
            return trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower($s)))), '-');
        };

        $first = $slug($firstName) ?: 'user';
        $last  = $slug($lastName);
        $base  = $last ? "{$first}-{$last}" : $first;

        return "{$base}-{$userId}";
    }
}
