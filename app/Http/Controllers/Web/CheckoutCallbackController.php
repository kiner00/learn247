<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
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
     * Uses an HMAC token (instead of a signed URL) so that extra query params
     * appended by Xendit after payment do not invalidate the link.
     * We log the user in with remember=true for better persistence in
     * mobile in-app browsers (Messenger, Instagram, etc.).
     */
    public function __invoke(Request $request, int $user, string $community): mixed
    {
        $expires = (int) $request->query('expires', 0);
        $token   = $request->query('token', '');

        if ($expires < now()->getTimestamp()) {
            abort(403, 'Checkout link has expired.');
        }

        $expected = hash_hmac('sha256', "{$user}|{$community}|{$expires}", config('app.key'));

        if (! hash_equals($expected, $token)) {
            abort(403, 'Invalid checkout link.');
        }

        $userModel      = User::findOrFail($user);
        $communityModel = Community::where('slug', $community)->firstOrFail();

        Auth::login($userModel, true);

        $refCode      = $request->cookie('ref_code');
        $affPixels    = ['affiliateFbPixelId' => null, 'affiliateTiktokPixelId' => null, 'affiliateGaId' => null];
        if ($refCode) {
            $aff = Affiliate::where('code', $refCode)
                ->where('community_id', $communityModel->id)
                ->where('status', Affiliate::STATUS_ACTIVE)
                ->first(['facebook_pixel_id', 'tiktok_pixel_id', 'google_analytics_id']);
            if ($aff) {
                $affPixels = [
                    'affiliateFbPixelId'    => $aff->facebook_pixel_id,
                    'affiliateTiktokPixelId' => $aff->tiktok_pixel_id,
                    'affiliateGaId'          => $aff->google_analytics_id,
                ];
            }
        }

        return Inertia::render('CheckoutProcessing', array_merge([
            'communitySlug'     => $communityModel->slug,
            'communityName'     => $communityModel->name,
            'pixelId'           => $communityModel->facebook_pixel_id,
            'tiktokPixelId'     => $communityModel->tiktok_pixel_id,
            'googleAnalyticsId' => $communityModel->google_analytics_id,
            'amount'            => (float) $communityModel->price,
            'currency'          => $communityModel->currency ?? 'PHP',
        ], $affPixels));
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
