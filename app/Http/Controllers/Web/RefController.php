<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

class RefController extends Controller
{
    public function redirect(string $code): RedirectResponse
    {
        $affiliate = Affiliate::where('code', $code)
            ->where('status', Affiliate::STATUS_ACTIVE)
            ->with('community')
            ->first();

        if (! $affiliate) {
            return redirect()->route('communities.index');
        }

        Cookie::queue('ref_code', $code, 60 * 24 * 30); // 30 days

        // Store the community URL as the "intended" destination so that after
        // login or registration, the user is sent back to this specific community.
        $communityUrl = route('communities.show', $affiliate->community->slug);
        session()->put('url.intended', $communityUrl);

        return redirect($communityUrl);
    }
}
