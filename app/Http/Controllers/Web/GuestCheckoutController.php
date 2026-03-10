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

class GuestCheckoutController extends Controller
{
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
            'phone'      => ['required', 'string', 'max:30'],
        ]);

        $community = $affiliate->community;

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            $user = User::create([
                'name'                 => trim($data['first_name'] . ' ' . $data['last_name']),
                'email'                => $data['email'],
                'phone'                => $data['phone'],
                'password'             => Hash::make(Str::random(32)),
                'needs_password_setup' => true,
            ]);
            $user->update(['username' => $this->generateUsername($data['first_name'], $data['last_name'], $user->id)]);
        }

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
        $slug = fn(string $s) => trim(
            preg_replace('/-+/', '-', preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower($s)))),
            '-'
        );

        $first = $slug($firstName) ?: 'user';
        $last  = $slug($lastName);
        $base  = $last ? "{$first}-{$last}" : $first;

        return "{$base}-{$userId}";
    }
}
