<?php

namespace App\Http\Controllers\Api;

use App\Actions\Account\CancelAccountDeletion;
use App\Actions\Account\LogoutEverywhere;
use App\Actions\Account\RequestAccountDeletion;
use App\Actions\Account\UpdateChatPrefs;
use App\Actions\Account\UpdateCommunityChat;
use App\Actions\Account\UpdateCommunityNotificationPrefs;
use App\Actions\Account\UpdateCrypto;
use App\Actions\Account\UpdateEmail;
use App\Actions\Account\UpdateMembershipVisibility;
use App\Actions\Account\UpdateNotificationPrefs;
use App\Actions\Account\UpdatePassword;
use App\Actions\Account\UpdatePayout;
use App\Actions\Account\UpdateProfile;
use App\Actions\Account\UpdateTheme;
use App\Actions\Account\UpdateTimezone;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelAccountDeletionRequest;
use App\Http\Requests\UpdateEmailRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\AccountDeletionStatusResource;
use App\Queries\Account\GetAccountSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountSettingsController extends Controller
{
    public function show(Request $request, GetAccountSettings $query): JsonResponse
    {
        return response()->json($query->execute($request->user(), $request->get('tab', 'communities')));
    }

    public function updateProfile(UpdateProfileRequest $request, UpdateProfile $action): JsonResponse
    {
        $action->execute($request->user(), $request->validated(), $request->file('avatar'));

        return response()->json(['message' => 'Profile updated.']);
    }

    public function updateMembershipVisibility(Request $request, int $communityId, UpdateMembershipVisibility $action): JsonResponse
    {
        $request->validate(['is_public' => ['required', 'boolean']]);
        $action->execute($request->user(), $communityId, $request->boolean('is_public'));

        return response()->json(['message' => 'Visibility updated.']);
    }

    public function updateEmail(UpdateEmailRequest $request, UpdateEmail $action): JsonResponse
    {
        $action->execute($request->user(), $request->validated()['email']);

        return response()->json(['message' => 'Email updated.']);
    }

    public function updatePassword(UpdatePasswordRequest $request, UpdatePassword $action): JsonResponse
    {
        $validated = $request->validated();
        $action->execute($request->user(), $validated['current_password'], $validated['password']);

        return response()->json(['message' => 'Password updated.']);
    }

    public function updateTimezone(Request $request, UpdateTimezone $action): JsonResponse
    {
        $request->validate(['timezone' => ['required', 'string', 'timezone']]);
        $action->execute($request->user(), $request->input('timezone'));

        return response()->json(['message' => 'Timezone updated.']);
    }

    public function logoutEverywhere(Request $request, LogoutEverywhere $action): JsonResponse
    {
        $action->execute($request->user());

        return response()->json(['message' => 'All other sessions have been logged out.']);
    }

    public function updateNotifications(Request $request, UpdateNotificationPrefs $action): JsonResponse
    {
        $action->execute($request->user(), $request->all());

        return response()->json(['message' => 'Notification preferences updated.']);
    }

    public function updateCommunityNotifications(Request $request, int $communityId, UpdateCommunityNotificationPrefs $action): JsonResponse
    {
        $action->execute($request->user(), $communityId, $request->all());

        return response()->json(['message' => 'Community notification preferences updated.']);
    }

    public function updateChat(Request $request, UpdateChatPrefs $action): JsonResponse
    {
        $action->execute($request->user(), $request->all());

        return response()->json(['message' => 'Chat preferences updated.']);
    }

    public function updateCommunityChat(Request $request, int $communityId, UpdateCommunityChat $action): JsonResponse
    {
        $request->validate(['chat_enabled' => ['required', 'boolean']]);
        $action->execute($request->user(), $communityId, $request->boolean('chat_enabled'));

        return response()->json(['message' => 'Community chat preferences updated.']);
    }

    public function updateTheme(Request $request, UpdateTheme $action): JsonResponse
    {
        $request->validate(['theme' => ['required', 'string', 'in:light,dark,system']]);
        $action->execute($request->user(), $request->input('theme'));

        return response()->json(['message' => 'Theme updated.']);
    }

    public function updatePayout(Request $request, UpdatePayout $action): JsonResponse
    {
        $action->execute($request->user(), $request->all());

        return response()->json(['message' => 'Payout details updated.']);
    }

    public function updateCrypto(Request $request, UpdateCrypto $action): JsonResponse
    {
        $request->validate(['crypto_wallet' => ['nullable', 'string', 'max:255']]);
        $action->execute($request->user(), $request->input('crypto_wallet'));

        return response()->json(['message' => 'Crypto wallet updated.']);
    }

    public function deleteAccount(Request $request, RequestAccountDeletion $action): JsonResponse
    {
        $user = $action->execute($request->user());

        return response()->json([
            'message' => 'Your account is scheduled for deletion. You can cancel within '.\App\Models\User::DELETION_GRACE_DAYS.' days by signing in again.',
            'deletion' => new AccountDeletionStatusResource($user),
        ]);
    }

    public function cancelDeletion(CancelAccountDeletionRequest $request, CancelAccountDeletion $action): JsonResponse
    {
        $user = $action->execute(
            $request->validated('email'),
            $request->validated('password'),
        );

        return response()->json([
            'message' => 'Your account has been restored. Please sign in.',
            'deletion' => new AccountDeletionStatusResource($user),
        ]);
    }

    public function deletionStatus(Request $request): AccountDeletionStatusResource
    {
        return new AccountDeletionStatusResource($request->user());
    }
}
