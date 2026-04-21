<?php

namespace App\Http\Controllers\Api;

use App\Actions\Billing\CancelRecurringPlan;
use App\Actions\Billing\CheckCurzzoPurchaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CurzzoPurchaseStatusResource;
use App\Models\CurzzoPurchase;
use Illuminate\Http\Request;

class CurzzoPurchaseController extends Controller
{
    public function checkStatus(Request $request, CurzzoPurchase $curzzoPurchase, CheckCurzzoPurchaseStatus $action): CurzzoPurchaseStatusResource
    {
        abort_unless($curzzoPurchase->user_id === $request->user()->id, 403);

        return new CurzzoPurchaseStatusResource($action->execute($curzzoPurchase));
    }

    public function cancelRecurring(Request $request, CurzzoPurchase $curzzoPurchase, CancelRecurringPlan $action): CurzzoPurchaseStatusResource
    {
        abort_unless($curzzoPurchase->user_id === $request->user()->id, 403);
        abort_unless($curzzoPurchase->isRecurring(), 400, 'This purchase is not recurring.');

        $action->execute($curzzoPurchase);

        return new CurzzoPurchaseStatusResource($curzzoPurchase->fresh());
    }
}
