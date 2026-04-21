<?php

namespace App\Http\Controllers\Api;

use App\Actions\Coupon\RedeemCoupon;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CouponController extends Controller
{
    public function redeem(Request $request, string $code, RedeemCoupon $action): JsonResponse
    {
        try {
            $subscription = $action->execute($request->user(), $code);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json([
            'message' => 'Coupon redeemed.',
            'plan' => $subscription->plan,
            'expires_at' => $subscription->expires_at?->toIso8601String(),
        ]);
    }
}
