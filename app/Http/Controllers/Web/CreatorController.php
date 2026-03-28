<?php

namespace App\Http\Controllers\Web;

use App\Actions\Billing\StartCreatorPlanCheckout;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Queries\Creator\GetCreatorDashboard;
use App\Services\Analytics\CreatorAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class CreatorController extends Controller
{
    public function plan(): Response
    {
        $user = Auth::user();

        return Inertia::render('Creator/Plan', [
            'basicPrice'  => (float) Setting::get('creator_plan_basic_price', 499),
            'proPrice'    => (float) Setting::get('creator_plan_pro_price', 1999),
            'currentPlan' => $user->creatorPlan(),
        ]);
    }

    public function planCheckout(Request $request, StartCreatorPlanCheckout $action)
    {
        $user = Auth::user();
        $plan = $request->validate(['plan' => ['required', 'in:basic,pro']])['plan'];

        try {
            $result = $action->execute($user, $plan);

            return response()->json(['checkout_url' => $result['checkout_url']]);
        } catch (\Throwable $e) {
            Log::error('CreatorController@planCheckout failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);
            return response()->json(['error' => 'Failed to start checkout.'], 500);
        }
    }

    public function dashboard(GetCreatorDashboard $query, CreatorAnalyticsService $analyticsService): Response
    {
        $user = Auth::user();

        try {
            $data        = $query->execute($user);
            $currentPlan = $user->creatorPlan();
            $analytics   = in_array($currentPlan, ['basic', 'pro']) ? $analyticsService->build($user->id) : null;

            return Inertia::render('Creator/Dashboard', array_merge($data, [
                'analytics'   => $analytics,
                'currentPlan' => $currentPlan,
            ]));
        } catch (\Throwable $e) {
            Log::error('CreatorController@dashboard failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);
            return Inertia::render('Creator/Dashboard', [
                'communities'    => [],
                'requestHistory' => [],
                'error'          => 'Failed to load dashboard data.',
            ]);
        }
    }
}
