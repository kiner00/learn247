<?php

namespace App\Http\Controllers\Api;

use App\Actions\Billing\HandleXenditWebhook;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class XenditWebhookController extends Controller
{
    public function __invoke(Request $request, HandleXenditWebhook $action): JsonResponse
    {
        $action->execute($request);

        return response()->json(['message' => 'Webhook processed.']);
    }
}
