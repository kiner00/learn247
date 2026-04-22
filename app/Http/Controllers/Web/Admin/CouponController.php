<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
                    'id' => $c->id,
                    'code' => $c->code,
                    'type' => $c->type,
                    'plan' => $c->plan,
                    'applies_to' => $c->applies_to,
                    'discount_percent' => $c->discount_percent !== null ? (float) $c->discount_percent : null,
                    'duration_months' => $c->duration_months,
                    'max_redemptions' => $c->max_redemptions,
                    'times_redeemed' => $c->times_redeemed,
                    'expires_at' => $c->expires_at?->format('Y-m-d'),
                    'is_active' => $c->is_active,
                    'created_at' => $c->created_at->format('M d, Y'),
                ]),
            'annualBaselinePercent' => Coupon::ANNUAL_BASELINE_DISCOUNT_PERCENT,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['type' => $request->input('type', Coupon::TYPE_PLAN_GRANT)]);
        $isDiscount = $request->input('type') === Coupon::TYPE_DISCOUNT;

        $rules = [
            'code' => 'required|string|max:32|unique:coupons,code',
            'type' => ['required', Rule::in([Coupon::TYPE_PLAN_GRANT, Coupon::TYPE_DISCOUNT])],
            'plan' => ['required', Rule::in(['basic', 'pro', Coupon::PLAN_BOTH])],
            'max_redemptions' => 'required|integer|min:1',
            'expires_at' => 'nullable|date|after:today',
        ];

        if ($isDiscount) {
            $rules['applies_to'] = ['required', Rule::in([Coupon::APPLIES_TO_MONTHLY, Coupon::APPLIES_TO_ANNUAL, Coupon::APPLIES_TO_BOTH])];
            $rules['discount_percent'] = 'required|numeric|min:0.01|max:100';
            $rules['duration_months'] = 'prohibited';
        } else {
            $rules['duration_months'] = 'required|integer|min:1|max:36';
            $rules['applies_to'] = 'prohibited';
            $rules['discount_percent'] = 'prohibited';
        }

        $data = $request->validate($rules);

        if ($isDiscount) {
            $this->validateMinDiscountForAnnual($data['applies_to'], (float) $data['discount_percent']);
        } elseif ($data['plan'] === Coupon::PLAN_BOTH) {
            return back()->withErrors(['plan' => 'Plan-grant coupons must target a specific plan (basic or pro), not both.']);
        }

        $data['code'] = strtoupper(trim($data['code']));

        Coupon::create($data);

        return back()->with('success', "Coupon {$data['code']} created.");
    }

    public function toggle(Coupon $coupon): RedirectResponse
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return back()->with('success', "Coupon {$coupon->code} ".($coupon->is_active ? 'activated' : 'deactivated').'.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        abort_if($coupon->times_redeemed > 0, 422, 'Cannot delete a coupon that has been redeemed.');

        $coupon->delete();

        return back()->with('success', "Coupon {$coupon->code} deleted.");
    }

    /**
     * Discount coupons targeting annual must beat the built-in 2-months-free
     * (≈16.67%). Otherwise the coupon would give users a WORSE deal than
     * the default annual pricing, which would be confusing to apply.
     */
    private function validateMinDiscountForAnnual(string $appliesTo, float $percent): void
    {
        $appliesToAnnual = in_array($appliesTo, [Coupon::APPLIES_TO_ANNUAL, Coupon::APPLIES_TO_BOTH]);
        if (! $appliesToAnnual) {
            return;
        }

        if ($percent <= Coupon::ANNUAL_BASELINE_DISCOUNT_PERCENT) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'discount_percent' => sprintf(
                    'Annual discount must exceed %s%% (the default 2-months-free). Enter a higher percentage.',
                    number_format(Coupon::ANNUAL_BASELINE_DISCOUNT_PERCENT, 2),
                ),
            ]);
        }
    }
}
