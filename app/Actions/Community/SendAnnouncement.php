<?php

namespace App\Actions\Community;

use App\Mail\AnnouncementMail;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class SendAnnouncement
{
    public function execute(User $sender, Community $community, string $subject, string $message): int
    {
        $members = $community->members()->with('user:id,name,email')->get();

        foreach ($members as $membership) {
            if ($membership->user && $membership->user->email) {
                Mail::to($membership->user->email)
                    ->queue(new AnnouncementMail($community, $sender, $subject, $message));
            }
        }

        return $members->count();
    }
}
