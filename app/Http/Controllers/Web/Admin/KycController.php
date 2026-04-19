<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Mail\KycResultMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class KycController extends Controller
{
    public function toggle(User $user): RedirectResponse
    {
        $wasVerified = $user->isKycVerified();

        $user->update([
            'kyc_verified_at' => $wasVerified ? null : now(),
            'kyc_status' => $wasVerified ? User::KYC_NONE : User::KYC_APPROVED,
            'kyc_rejected_reason' => null,
        ]);

        $status = $wasVerified ? 'unverified' : 'verified';

        return back()->with('success', "User {$user->name} is now KYC {$status}.");
    }

    public function reviews(Request $request): Response
    {
        $status = $request->input('status', 'submitted');

        $users = User::where('kyc_status', $status)
            ->latest('kyc_submitted_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'username' => $u->username,
                'kyc_status' => $u->kyc_status,
                'kyc_id_document' => $u->kyc_id_document,
                'kyc_selfie' => $u->kyc_selfie,
                'submitted_at' => $u->kyc_submitted_at?->diffForHumans(),
                'rejected_reason' => $u->kyc_rejected_reason,
                'ai_result' => $u->kyc_ai_result,
                'ai_rejections' => $u->kyc_ai_rejections ?? 0,
            ]);

        $counts = [
            'submitted' => User::where('kyc_status', User::KYC_SUBMITTED)->count(),
            'approved' => User::where('kyc_status', User::KYC_APPROVED)->count(),
            'rejected' => User::where('kyc_status', User::KYC_REJECTED)->count(),
        ];

        return Inertia::render('Admin/KycReviews', [
            'users' => $users,
            'filters' => ['status' => $status],
            'counts' => $counts,
        ]);
    }

    public function approve(User $user): RedirectResponse
    {
        $user->update([
            'kyc_status' => User::KYC_APPROVED,
            'kyc_verified_at' => now(),
            'kyc_rejected_reason' => null,
        ]);

        Mail::to($user)->queue(new KycResultMail(
            user: $user,
            approved: true,
        ));

        return back()->with('success', "KYC approved for {$user->name}.");
    }

    public function reject(User $user, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $user->update([
            'kyc_status' => User::KYC_REJECTED,
            'kyc_verified_at' => null,
            'kyc_rejected_reason' => $data['reason'],
        ]);

        Mail::to($user)->queue(new KycResultMail(
            user: $user,
            approved: false,
            reason: $data['reason'],
        ));

        return back()->with('success', "KYC rejected for {$user->name}.");
    }
}
