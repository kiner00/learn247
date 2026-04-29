<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\CreatorPlanAffiliate\ApproveCreatorPlanAffiliateApplication;
use App\Actions\CreatorPlanAffiliate\RejectCreatorPlanAffiliateApplication;
use App\Http\Controllers\Controller;
use App\Models\CreatorPlanAffiliateApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreatorPlanAffiliateController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->input('status', CreatorPlanAffiliateApplication::STATUS_PENDING);

        $applications = CreatorPlanAffiliateApplication::with('user:id,name,email,username,avatar')
            ->where('status', $status)
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($app) => [
                'id' => $app->id,
                'status' => $app->status,
                'pitch' => $app->pitch,
                'rejection_reason' => $app->rejection_reason,
                'created_at' => $app->created_at?->diffForHumans(),
                'reviewed_at' => $app->reviewed_at?->diffForHumans(),
                'user' => [
                    'id' => $app->user->id,
                    'name' => $app->user->name,
                    'email' => $app->user->email,
                    'username' => $app->user->username,
                    'avatar' => $app->user->avatar,
                ],
            ]);

        $counts = [
            'pending' => CreatorPlanAffiliateApplication::pending()->count(),
            'approved' => CreatorPlanAffiliateApplication::where('status', CreatorPlanAffiliateApplication::STATUS_APPROVED)->count(),
            'rejected' => CreatorPlanAffiliateApplication::where('status', CreatorPlanAffiliateApplication::STATUS_REJECTED)->count(),
        ];

        return Inertia::render('Admin/CreatorPlanAffiliates', [
            'applications' => $applications,
            'filters' => ['status' => $status],
            'counts' => $counts,
        ]);
    }

    public function approve(Request $request, CreatorPlanAffiliateApplication $application, ApproveCreatorPlanAffiliateApplication $action): RedirectResponse
    {
        $action->execute($application, $request->user());

        return back()->with('success', "Approved {$application->user->name} as a Creator Plan affiliate.");
    }

    public function reject(Request $request, CreatorPlanAffiliateApplication $application, RejectCreatorPlanAffiliateApplication $action): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $action->execute($application, $request->user(), $data['reason']);

        return back()->with('success', "Rejected application from {$application->user->name}.");
    }
}
