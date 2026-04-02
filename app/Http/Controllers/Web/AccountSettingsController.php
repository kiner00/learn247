<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\LogoutEverywhere;
use App\Actions\Account\UpdateChatPrefs;
use App\Actions\Account\UpdateCommunityChat;
use App\Actions\Account\UpdateCommunityNotificationPrefs;
use App\Actions\Account\UpdateCrypto;
use App\Actions\Account\UpdateEmail;
use App\Models\User;
use App\Actions\Account\UpdateMembershipVisibility;
use App\Actions\Account\UpdateNotificationPrefs;
use App\Actions\Account\UpdatePassword;
use App\Actions\Account\UpdatePayout;
use App\Actions\Account\UpdateProfile;
use App\Actions\Account\UpdateTheme;
use App\Actions\Account\UpdateTimezone;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEmailRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Queries\Account\GetAccountSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountSettingsController extends Controller
{
    public function show(Request $request, GetAccountSettings $query): Response
    {
        $data = $query->execute($request->user(), $request->query('tab', 'communities'));

        return Inertia::render('Account/Settings', $data);
    }

    public function updateProfile(UpdateProfileRequest $request, UpdateProfile $action): RedirectResponse
    {
        $action->execute($request->user(), $request->validated(), $request->file('avatar'));

        return back()->with('success', 'Profile updated!');
    }

    public function updateMembershipVisibility(Request $request, int $communityId, UpdateMembershipVisibility $action): RedirectResponse
    {
        $request->validate(['show_on_profile' => ['required', 'boolean']]);

        $action->execute($request->user(), $communityId, $request->boolean('show_on_profile'));

        return back()->with('success', 'Membership visibility updated!');
    }

    public function updateEmail(UpdateEmailRequest $request, UpdateEmail $action): RedirectResponse
    {
        $action->execute($request->user(), $request->validated('email'));

        return back()->with('success', 'Email updated!');
    }

    public function updatePassword(UpdatePasswordRequest $request, UpdatePassword $action): RedirectResponse
    {
        $action->execute($request->user(), $request->validated('current_password'), $request->validated('password'));

        return back()->with('success', 'Password updated!');
    }

    public function updateTimezone(Request $request, UpdateTimezone $action): RedirectResponse
    {
        $data = $request->validate(['timezone' => ['required', 'string', 'timezone']]);

        $action->execute($request->user(), $data['timezone']);

        return back()->with('success', 'Timezone saved!');
    }

    public function logoutEverywhere(Request $request, LogoutEverywhere $action): RedirectResponse
    {
        $action->execute($request->user());
        $request->session()->regenerate();

        return back()->with('success', 'Logged out of all other devices.');
    }

    public function updateNotifications(Request $request, UpdateNotificationPrefs $action): RedirectResponse
    {
        $data = $request->validate([
            'follower'  => ['required', 'boolean'],
            'likes'     => ['required', 'boolean'],
            'kaching'   => ['required', 'boolean'],
            'affiliate' => ['required', 'boolean'],
        ]);

        $action->execute($request->user(), $data);

        return back()->with('success', 'Notification preferences saved!');
    }

    public function updateCommunityNotifications(Request $request, int $communityId, UpdateCommunityNotificationPrefs $action): RedirectResponse
    {
        $data = $request->validate([
            'new_posts' => ['required', 'boolean'],
            'comments'  => ['required', 'boolean'],
            'mentions'  => ['required', 'boolean'],
        ]);

        $action->execute($request->user(), $communityId, $data);

        return back()->with('success', 'Community notification preferences saved!');
    }

    public function updateChat(Request $request, UpdateChatPrefs $action): RedirectResponse
    {
        $data = $request->validate([
            'notifications'       => ['required', 'boolean'],
            'email_notifications' => ['required', 'boolean'],
        ]);

        $action->execute($request->user(), $data);

        return back()->with('success', 'Chat preferences saved!');
    }

    public function updateCommunityChat(Request $request, int $communityId, UpdateCommunityChat $action): RedirectResponse
    {
        $request->validate(['chat_enabled' => ['required', 'boolean']]);

        $action->execute($request->user(), $communityId, $request->boolean('chat_enabled'));

        return back()->with('success', 'Chat preference saved!');
    }

    public function updateTheme(Request $request, UpdateTheme $action): RedirectResponse
    {
        $data = $request->validate(['theme' => ['required', 'string', 'in:light,dark']]);

        $action->execute($request->user(), $data['theme']);

        return back()->with('success', 'Theme saved!');
    }

    public function updatePayout(Request $request, UpdatePayout $action): RedirectResponse
    {
        $data = $request->validate([
            'payout_method'  => ['required', 'string', 'in:gcash,maya,bank,paypal'],
            'payout_details' => ['required', 'string', 'max:255'],
            'bank_name'      => ['nullable', 'string', 'max:100'],
        ]);

        $action->execute($request->user(), $data);

        return back()->with('success', 'Payout details saved!');
    }

    public function updateCrypto(Request $request, UpdateCrypto $action): RedirectResponse
    {
        $data = $request->validate(['crypto_wallet' => ['nullable', 'string', 'max:255']]);

        $action->execute($request->user(), $data['crypto_wallet'] ?? null);

        return back()->with('success', 'Crypto wallet saved!');
    }

    public function submitKyc(Request $request, \App\Services\StorageService $storage): RedirectResponse
    {
        $user = $request->user();

        if ($user->kyc_status === User::KYC_SUBMITTED) {
            return back()->withErrors(['kyc' => 'Your KYC is already under review.']);
        }

        if ($user->kyc_status === User::KYC_APPROVED) {
            return back()->withErrors(['kyc' => 'Your KYC is already approved.']);
        }

        $request->validate([
            'id_document' => ['required', 'image', 'max:10240'],
            'selfie'      => ['required', 'image', 'max:10240'],
        ]);

        $idUrl     = $storage->upload($request->file('id_document'), 'kyc-documents');
        $selfieUrl = $storage->upload($request->file('selfie'), 'kyc-documents');

        $user->update([
            'kyc_status'          => User::KYC_SUBMITTED,
            'kyc_id_document'     => $idUrl,
            'kyc_selfie'          => $selfieUrl,
            'kyc_submitted_at'    => now(),
            'kyc_rejected_reason' => null,
        ]);

        return back()->with('success', 'KYC documents submitted! We\'ll review them shortly.');
    }
}
