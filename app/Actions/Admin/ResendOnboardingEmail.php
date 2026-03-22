<?php

namespace App\Actions\Admin;

use App\Mail\TempPasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ResendOnboardingEmail
{
    public function execute(User $user): void
    {
        abort_unless($user->needs_password_setup, 422, 'User has already set their password.');

        $community = $user->communityMemberships()->with('community')->first()?->community;
        abort_unless($community, 422, 'No community found for this user.');

        $tempPassword = 'Tmp@' . Str::upper(Str::random(3)) . Str::random(3);
        $user->forceFill(['password' => Hash::make($tempPassword)])->save();

        Mail::to($user->email)->queue(new TempPasswordMail($user, $tempPassword, $community));
    }
}
