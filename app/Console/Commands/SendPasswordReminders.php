<?php

namespace App\Console\Commands;

use App\Mail\PasswordReminderMail;
use App\Mail\TempPasswordMail;
use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendPasswordReminders extends Command
{
    protected $signature = 'passwords:send-reminders';

    protected $description = 'Send password change reminders and re-issue temp passwords for guest users';

    public function handle(): void
    {
        // Day 3: send reminder email
        $day3Users = User::where('needs_password_setup', true)
            ->whereBetween('created_at', [now()->subDays(4), now()->subDays(3)])
            ->get();

        foreach ($day3Users as $user) {
            Mail::to($user->email)->queue(new PasswordReminderMail($user));
            $this->line("Reminder sent: {$user->email}");
        }

        // Day 5: generate new temp password and re-send
        $day5Users = User::where('needs_password_setup', true)
            ->whereBetween('created_at', [now()->subDays(6), now()->subDays(5)])
            ->get();

        foreach ($day5Users as $user) {
            $tempPassword = 'Tmp@'.Str::upper(Str::random(3)).Str::random(3);
            $user->forceFill(['password' => Hash::make($tempPassword)])->save();

            // Find most recent community for the email context
            $community = Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->latest()
                ->first()
                ?->community;

            if ($community) {
                Mail::to($user->email)->queue(new TempPasswordMail($user, $tempPassword, $community));
                $this->line("New temp password sent: {$user->email}");
            }
        }

        $this->info("Done. Reminders: {$day3Users->count()}, New temp passwords: {$day5Users->count()}");
    }
}
