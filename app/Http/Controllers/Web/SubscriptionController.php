<?php

namespace App\Http\Controllers\Web;

use App\Actions\Billing\StartSubscriptionCheckout;
use App\Http\Controllers\Controller;
use App\Models\Community;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function checkout(Request $request, Community $community, StartSubscriptionCheckout $action): mixed
    {
        $result = $action->execute($request->user(), $community);

        // Inertia::location triggers a full-page browser redirect (works for external URLs)
        return Inertia::location($result['checkout_url']);
    }
}
