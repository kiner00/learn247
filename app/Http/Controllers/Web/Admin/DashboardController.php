<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Setting;
use App\Queries\Admin\AffiliateAnalytics;
use App\Queries\Admin\CreatorAnalytics;
use App\Services\Analytics\AdminDashboardService;
use App\Services\XenditService;
use App\Support\CacheKeys;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(XenditService $xendit, AdminDashboardService $service): Response
    {
        return Inertia::render('Admin/Dashboard', array_merge($service->build(), [
            'xenditBalance' => $xendit->getBalance(),
            'creatorPlanPricing' => [
                'basic_price' => (float) Setting::get('creator_plan_basic_price', 499),
                'pro_price' => (float) Setting::get('creator_plan_pro_price', 1999),
                'basic_annual_price' => (float) Setting::get('creator_plan_basic_annual_price', 4990),
                'pro_annual_price' => (float) Setting::get('creator_plan_pro_annual_price', 19990),
            ],
        ]));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $request->validate(['app_theme' => 'required|in:green,yellow']);
        Setting::set('app_theme', $request->app_theme);

        return back()->with('success', 'Theme updated.');
    }

    public function updateCreatorPlanPricing(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'basic_price' => 'required|numeric|min:0',
            'pro_price' => 'required|numeric|min:0',
            'basic_annual_price' => 'required|numeric|min:0',
            'pro_annual_price' => 'required|numeric|min:0',
        ]);

        Setting::set('creator_plan_basic_price', (string) $data['basic_price']);
        Setting::set('creator_plan_pro_price', (string) $data['pro_price']);
        Setting::set('creator_plan_basic_annual_price', (string) $data['basic_annual_price']);
        Setting::set('creator_plan_pro_annual_price', (string) $data['pro_annual_price']);

        return back()->with('success', 'Creator plan pricing updated.');
    }

    public function toggleFeatured(Community $community): RedirectResponse
    {
        $community->update(['is_featured' => ! $community->is_featured]);
        CacheKeys::flushAdmin();

        return back()->with('success', "{$community->name} is now ".($community->is_featured ? 'featured' : 'unfeatured').'.');
    }

    public function creatorAnalytics(Request $request, CreatorAnalytics $query): Response
    {
        return Inertia::render('Admin/CreatorAnalytics', $query->execute(
            $request->string('search')->trim()->toString(),
            $request->string('plan')->trim()->toString(),
        ));
    }

    public function affiliateAnalytics(Request $request, AffiliateAnalytics $query): Response
    {
        return Inertia::render('Admin/AffiliateAnalytics', $query->execute(
            $request->string('search')->trim()->toString(),
            $request->string('status')->trim()->toString(),
        ));
    }
}
