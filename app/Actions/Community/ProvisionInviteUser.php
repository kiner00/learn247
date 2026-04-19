<?php

namespace App\Actions\Community;

use App\Mail\TempPasswordMail;
use App\Models\CommunityInvite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ProvisionInviteUser
{
    /**
     * Find or create the user for an invite email, send temp password if needed,
     * then log them in. Returns the authenticated User.
     */
    public function execute(CommunityInvite $invite): User
    {
        $user = User::where('email', $invite->email)->first();

        if (! $user) {
            $tempPassword = Str::random(12);
            $user = User::create([
                'name' => explode('@', $invite->email)[0],
                'email' => $invite->email,
                'password' => bcrypt($tempPassword),
                'needs_password_setup' => true,
            ]);
            Mail::to($user)->send(new TempPasswordMail($user, $tempPassword, $invite->community));
        } elseif ($user->needs_password_setup) {
            $tempPassword = Str::random(12);
            $user->update(['password' => bcrypt($tempPassword)]);
            Mail::to($user)->send(new TempPasswordMail($user, $tempPassword, $invite->community));
        }

        Auth::login($user);
        request()->session()->regenerate();

        return $user;
    }
}
