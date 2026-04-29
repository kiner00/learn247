<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreatorPlanAffiliate\ApplyForCreatorPlanAffiliate;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CreatorPlanAffiliateController extends Controller
{
    public function apply(Request $request, ApplyForCreatorPlanAffiliate $action): RedirectResponse
    {
        $data = $request->validate([
            'pitch' => ['nullable', 'string', 'max:1000'],
        ]);

        $action->execute($request->user(), $data['pitch'] ?? null);

        return back()->with('success', 'Application submitted. We will review it shortly.');
    }
}
