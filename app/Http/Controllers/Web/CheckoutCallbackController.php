<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CheckoutCallbackController extends Controller
{
    /**
     * Xendit success redirect handler.
     *
     * The URL is signed (temp, 2h) and contains the user + community slug.
     * We log the user in immediately so they land authenticated, then render
     * a processing screen that polls until the webhook activates their subscription.
     */
    public function __invoke(Request $request, int $user, string $community): mixed
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired checkout link.');
        }

        $userModel = User::findOrFail($user);
        $communityModel = Community::where('slug', $community)->firstOrFail();

        // Log in without touching the session guard's "intended" URL
        Auth::login($userModel, false);

        return Inertia::render('CheckoutProcessing', [
            'communitySlug' => $communityModel->slug,
            'communityName' => $communityModel->name,
        ]);
    }

    /**
     * Polling endpoint: returns whether the subscription is now active.
     */
    public function status(Request $request, Community $community): \Illuminate\Http\JsonResponse
    {
        $active = Subscription::where('community_id', $community->id)
            ->where('user_id', $request->user()->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->exists();

        return response()->json(['active' => $active]);
    }
}
