<?php

namespace App\Http\Controllers\Web;

use App\Actions\Admin\ApprovePayoutRequest;
use App\Actions\Admin\BatchPayAffiliates;
use App\Actions\Admin\RejectPayoutRequest;
use App\Actions\Admin\ResendOnboardingEmail;
use App\Actions\Admin\SendGlobalAnnouncement;
use App\Actions\Admin\ToggleUserStatus;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Coupon;
use App\Models\EmailTemplate;
use App\Models\PayoutRequest;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Queries\Admin\AffiliateAnalytics;
use App\Queries\Admin\CreatorAnalytics;
use App\Queries\Admin\GetPayoutsDashboard;
use App\Queries\Admin\ListTrashedPosts;
use App\Queries\Admin\ListUsers;
use App\Services\Admin\EmailTemplateService;
use App\Services\Analytics\AdminDashboardService;
use App\Services\Payout\OwnerPayoutDispatcher;
use App\Services\XenditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard(XenditService $xendit, AdminDashboardService $service): Response
    {
        return Inertia::render('Admin/Dashboard', array_merge($service->build(), [
            'xenditBalance'      => $xendit->getBalance(),
            'creatorPlanPricing' => [
                'basic_price' => (float) Setting::get('creator_plan_basic_price', 499),
                'pro_price'   => (float) Setting::get('creator_plan_pro_price', 1999),
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
            'pro_price'   => 'required|numeric|min:0',
        ]);

        Setting::set('creator_plan_basic_price', (string) $data['basic_price']);
        Setting::set('creator_plan_pro_price',   (string) $data['pro_price']);

        return back()->with('success', 'Creator plan pricing updated.');
    }

    // ── Payouts ───────────────────────────────────────────────────────────────

    public function payouts(XenditService $xendit, GetPayoutsDashboard $query): Response
    {
        return Inertia::render('Admin/Payouts', array_merge(
            $query->execute(),
            ['xenditBalance' => $xendit->getBalance()]
        ));
    }

    public function payOwner(Community $community, OwnerPayoutDispatcher $dispatcher): RedirectResponse
    {
        abort_unless($dispatcher->canDispatch($community), 422, 'Owner has no payout details set.');

        try {
            $result = $dispatcher->dispatch($community);
            return back()->with('success', "Paid ₱" . number_format($result['amount'], 2) . " to {$community->owner->name} via Xendit.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function batchPayOwners(OwnerPayoutDispatcher $dispatcher): RedirectResponse
    {
        $result = $dispatcher->batchDispatch(
            Community::with('owner')->where('price', '>', 0)
        );

        return back()->with($result['errors'] ? 'error' : 'success', $result['message']);
    }

    public function paySelectedOwners(Request $request, OwnerPayoutDispatcher $dispatcher): RedirectResponse
    {
        $ids = $request->validate(['community_ids' => 'required|array', 'community_ids.*' => 'integer'])['community_ids'];

        $result = $dispatcher->batchDispatch(
            Community::with('owner')->whereIn('id', $ids)->get(),
            'selected owner(s)'
        );

        return back()->with($result['errors'] ? 'error' : 'success', $result['message']);
    }

    public function paySelectedAffiliates(Request $request, BatchPayAffiliates $action): RedirectResponse
    {
        $ids    = $request->validate(['affiliate_ids' => 'required|array', 'affiliate_ids.*' => 'integer'])['affiliate_ids'];
        $result = $action->execute(affiliateIds: $ids);

        return back()->with($result['errors'] ? 'error' : 'success', $result['message']);
    }

    public function batchPayAffiliates(BatchPayAffiliates $action): RedirectResponse
    {
        $result = $action->execute();

        return back()->with($result['errors'] ? 'error' : 'success', $result['message']);
    }

    public function approvePayoutRequest(PayoutRequest $payoutRequest, ApprovePayoutRequest $action): RedirectResponse
    {
        try {
            $action->execute($payoutRequest);
            return back()->with('success', "Approved & sent ₱" . number_format($payoutRequest->amount, 2) . " to {$payoutRequest->user->name}.");
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function rejectPayoutRequest(Request $request, PayoutRequest $payoutRequest, RejectPayoutRequest $action): RedirectResponse
    {
        $reason = $request->validate(['reason' => 'nullable|string|max:255'])['reason'] ?? null;
        $action->execute($payoutRequest, $reason);

        return back()->with('success', "Payout request #{$payoutRequest->id} rejected.");
    }

    public function markPayoutRequestPaid(PayoutRequest $payoutRequest): RedirectResponse
    {
        abort_unless(
            $payoutRequest->status === PayoutRequest::STATUS_APPROVED,
            422,
            'Only approved requests can be marked paid.'
        );

        if ($payoutRequest->type === PayoutRequest::TYPE_AFFILIATE) {
            $mark      = app(\App\Actions\Affiliate\MarkAffiliateConversionPaid::class);
            $remaining = (float) $payoutRequest->amount;

            \App\Models\AffiliateConversion::where('affiliate_id', $payoutRequest->affiliate_id)
                ->where('status', \App\Models\AffiliateConversion::STATUS_PENDING)
                ->orderBy('created_at')
                ->get()
                ->each(function ($conversion) use (&$remaining, $mark) {
                    if ($remaining <= 0) return false;
                    $mark->execute($conversion);
                    $remaining -= (float) $conversion->commission_amount;
                });
        }

        $payoutRequest->update(['status' => PayoutRequest::STATUS_PAID]);

        return back()->with('success', "Payout request #{$payoutRequest->id} marked as paid and conversions settled.");
    }

    // ── Onboarding ────────────────────────────────────────────────────────────

    public function resendOnboardingEmail(User $user, ResendOnboardingEmail $action): RedirectResponse
    {
        $action->execute($user);

        return back()->with('success', "Resent login email to {$user->email}.");
    }

    // ── Communities ───────────────────────────────────────────────────────────

    public function toggleFeatured(Community $community): RedirectResponse
    {
        $community->update(['is_featured' => ! $community->is_featured]);

        return back()->with('success', "{$community->name} is now " . ($community->is_featured ? 'featured' : 'unfeatured') . ".");
    }

    // ── Analytics ─────────────────────────────────────────────────────────────

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

    // ── Users ─────────────────────────────────────────────────────────────────

    public function users(Request $request, ListUsers $query): Response
    {
        return Inertia::render('Admin/Users', $query->execute(
            $request->string('search')->trim()->toString()
        ));
    }

    public function toggleUserStatus(User $user, ToggleUserStatus $action): RedirectResponse
    {
        $action->execute($user);

        return back()->with('success', "User {$user->name} has been " . ($user->is_active ? 'enabled' : 'disabled') . ".");
    }

    // ── Posts ─────────────────────────────────────────────────────────────────

    public function trashedPosts(Request $request, ListTrashedPosts $query): Response
    {
        return Inertia::render('Admin/TrashedPosts', $query->execute(
            $request->string('search')->trim()->toString()
        ));
    }

    public function restorePost(int $postId): RedirectResponse
    {
        Post::onlyTrashed()->findOrFail($postId)->restore();

        return back()->with('success', 'Post restored.');
    }

    public function forceDeletePost(int $postId): RedirectResponse
    {
        Post::onlyTrashed()->findOrFail($postId)->forceDelete();

        return back()->with('success', 'Post permanently deleted.');
    }

    // ── Email Templates ───────────────────────────────────────────────────────

    public function emailTemplates(): Response
    {
        return Inertia::render('Admin/EmailTemplates', [
            'templates' => EmailTemplate::orderBy('name')->get(['id', 'key', 'name', 'subject', 'updated_at']),
        ]);
    }

    public function editEmailTemplate(string $key): Response
    {
        return Inertia::render('Admin/EmailTemplateEdit', [
            'template' => EmailTemplate::where('key', $key)->firstOrFail(),
        ]);
    }

    public function updateEmailTemplate(Request $request, string $key): RedirectResponse
    {
        $data = $request->validate([
            'subject'   => 'required|string|max:255',
            'html_body' => 'required|string',
        ]);

        EmailTemplate::where('key', $key)->firstOrFail()->update($data);

        return back()->with('success', 'Email template saved.');
    }

    public function previewEmailTemplate(Request $request, string $key, EmailTemplateService $service): \Illuminate\Http\Response
    {
        $data = $request->validate([
            'subject'   => 'required|string|max:255',
            'html_body' => 'required|string',
        ]);

        return response($service->preview($key, $data['html_body']));
    }

    // ── Global Announcement ───────────────────────────────────────────────────

    public function globalAnnouncement(): Response
    {
        return Inertia::render('Admin/GlobalAnnouncement');
    }

    public function sendGlobalAnnouncement(Request $request, SendGlobalAnnouncement $action): RedirectResponse
    {
        $data = $request->validate([
            'subject'  => 'required|string|max:255',
            'message'  => 'required|string',
            'audience' => 'required|in:affiliates,creators,members,all',
        ]);

        $count = $action->execute($request->user(), $data['subject'], $data['message'], $data['audience']);

        return back()->with('success', "Announcement queued for {$count} recipients.");
    }

    // ── Coupons ──────────────────────────────────────────────────────────────

    public function coupons(): Response
    {
        return Inertia::render('Admin/Coupons', [
            'coupons' => Coupon::withCount('redemptions')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Coupon $c) => [
                    'id'              => $c->id,
                    'code'            => $c->code,
                    'plan'            => $c->plan,
                    'duration_months' => $c->duration_months,
                    'max_redemptions' => $c->max_redemptions,
                    'times_redeemed'  => $c->times_redeemed,
                    'expires_at'      => $c->expires_at?->format('Y-m-d'),
                    'is_active'       => $c->is_active,
                    'created_at'      => $c->created_at->format('M d, Y'),
                ]),
        ]);
    }

    public function storeCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code'            => 'required|string|max:32|unique:coupons,code',
            'plan'            => 'required|in:basic,pro',
            'duration_months' => 'required|integer|min:1|max:36',
            'max_redemptions' => 'required|integer|min:1',
            'expires_at'      => 'nullable|date|after:today',
        ]);

        $data['code'] = strtoupper(trim($data['code']));

        Coupon::create($data);

        return back()->with('success', "Coupon {$data['code']} created.");
    }

    public function toggleCoupon(Coupon $coupon): RedirectResponse
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return back()->with('success', "Coupon {$coupon->code} " . ($coupon->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function deleteCoupon(Coupon $coupon): RedirectResponse
    {
        abort_if($coupon->times_redeemed > 0, 422, 'Cannot delete a coupon that has been redeemed.');

        $coupon->delete();

        return back()->with('success', "Coupon {$coupon->code} deleted.");
    }
}
