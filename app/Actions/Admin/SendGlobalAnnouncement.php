<?php

namespace App\Actions\Admin;

use App\Mail\GlobalAnnouncementMail;
use App\Models\Affiliate;
use App\Models\CreatorSubscription;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class SendGlobalAnnouncement
{
    /**
     * @param  string  $audience  affiliates|creators|members|all
     */
    public function execute(User $sender, string $subject, string $message, string $audience): int
    {
        $recipients = $this->resolveRecipients($audience);

        foreach ($recipients as $user) {
            if ($user->email) {
                Mail::to($user->email)
                    ->queue(new GlobalAnnouncementMail($sender, $subject, $message));
            }
        }

        return $recipients->count();
    }

    private function resolveRecipients(string $audience): Collection
    {
        return match ($audience) {
            'affiliates' => User::whereIn('id',
                    Affiliate::where('status', Affiliate::STATUS_ACTIVE)->pluck('user_id')
                )->select('id', 'name', 'email')->get(),

            'creators' => User::whereIn('id',
                    CreatorSubscription::where('status', CreatorSubscription::STATUS_ACTIVE)->pluck('user_id')
                )->select('id', 'name', 'email')->get(),

            'members' => User::whereHas('communityMemberships')
                ->select('id', 'name', 'email')
                ->get(),

            default => User::where('is_active', true)
                ->select('id', 'name', 'email')
                ->get(),
        };
    }
}
