<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(): Response
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

    public function store(Request $request): RedirectResponse
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

    public function toggle(Coupon $coupon): RedirectResponse
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return back()->with('success', "Coupon {$coupon->code} " . ($coupon->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        abort_if($coupon->times_redeemed > 0, 422, 'Cannot delete a coupon that has been redeemed.');

        $coupon->delete();

        return back()->with('success', "Coupon {$coupon->code} deleted.");
    }
}
