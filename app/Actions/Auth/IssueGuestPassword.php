<?php

namespace App\Actions\Auth;

use App\Mail\TempPasswordMail;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class IssueGuestPassword
{
    /**
     * Generate and persist a temporary password for a guest user.
     *
     * Safe to call inside a DB transaction -- no mail is sent here.
     *
     * @return string|null  The temporary password, or null if the user does not need one.
     */
    public function generate(User $user): ?string
    {
        if (! $user->needs_password_setup) {
            return null;
        }

        $tempPassword = 'Tmp@' . Str::upper(Str::random(3)) . Str::random(3);
        $user->forceFill(['password' => Hash::make($tempPassword)])->save();

        Log::info('Guest temp password issued', [
            'email'    => $user->email,
            'password' => $tempPassword,
        ]);

        return $tempPassword;
    }

    /**
     * Queue the welcome email with the temporary password.
     *
     * Call this OUTSIDE a DB transaction so mail failures do not
     * roll back payment records.
     */
    public function sendEmail(User $user, string $tempPassword, Community $community): void
    {
        try {
            Mail::to($user->email)->queue(
                new TempPasswordMail($user, $tempPassword, $community)
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send guest temp password email', [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate a temp password and immediately queue the welcome email.
     *
     * Convenience method -- should be called OUTSIDE a DB transaction.
     *
     * @return string|null  The temporary password, or null if the user does not need one.
     */
    public function execute(User $user, Community $community): ?string
    {
        $tempPassword = $this->generate($user);

        if ($tempPassword) {
            $this->sendEmail($user, $tempPassword, $community);
        }

        return $tempPassword;
    }
}
