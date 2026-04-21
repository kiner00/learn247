<?php

namespace App\Http\Controllers\Api;

use App\Actions\Billing\StartCurzzoTopupCheckout;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Services\Community\CurzzoLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurzzoTopupController extends Controller
{
    public function packs(Community $community, CurzzoLimitService $limits): JsonResponse
    {
        return response()->json([
            'packs' => $limits->getPacks($community),
        ]);
    }

    public function checkout(Request $request, Community $community, StartCurzzoTopupCheckout $action, CurzzoLimitService $limits): JsonResponse
    {
        $request->validate([
            'pack_index' => ['required', 'integer', 'min:0'],
            'success_redirect_url' => ['nullable', 'url'],
        ]);

        $packs = $limits->getPacks($community);
        $index = (int) $request->input('pack_index');

        if (! isset($packs[$index])) {
            return response()->json(['error' => 'Invalid pack selected.'], 422);
        }

        $result = $action->execute(
            $request->user(),
            $community,
            $packs[$index],
            successRedirectUrl: $request->input('success_redirect_url'),
        );

        return response()->json([
            'topup_id' => $result['topup']->id,
            'checkout_url' => $result['checkout_url'],
        ]);
    }
}
