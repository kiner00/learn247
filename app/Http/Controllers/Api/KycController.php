<?php

namespace App\Http\Controllers\Api;

use App\Actions\Account\RequestManualKycReview;
use App\Actions\Account\SubmitKyc;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitKycRequest;
use App\Http\Resources\KycResource;
use Illuminate\Http\Request;

class KycController extends Controller
{
    public function submit(SubmitKycRequest $request, SubmitKyc $action): KycResource
    {
        $user = $action->execute(
            $request->user(),
            $request->file('id_document'),
            $request->file('selfie'),
        );

        return new KycResource($user);
    }

    public function status(Request $request): KycResource
    {
        return new KycResource($request->user());
    }

    public function manualReview(Request $request, RequestManualKycReview $action): KycResource
    {
        $user = $action->execute($request->user());

        return new KycResource($user);
    }
}
