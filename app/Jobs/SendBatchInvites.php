<?php

namespace App\Jobs;

use App\Mail\CommunityInviteMail;
use App\Models\Community;
use App\Models\CommunityInvite;
use App\Models\CommunityMember;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendBatchInvites implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly Community $community,
        public readonly array $emails,
        public readonly ?int $freeAccessMonths = null,
    ) {}

    public function handle(): void
    {
        foreach ($this->emails as $email) {
            // Skip if already a member
            $alreadyMember = CommunityMember::where('community_id', $this->community->id)
                ->whereHas('user', fn ($q) => $q->where('email', $email))
                ->exists();

            if ($alreadyMember) {
                continue;
            }

            $invite = CommunityInvite::updateOrCreate(
                ['community_id' => $this->community->id, 'email' => $email],
                [
                    'token'              => Str::random(64),
                    'accepted_at'        => null,
                    'expires_at'         => now()->addDays(7),
                    'free_access_months' => $this->freeAccessMonths,
                ]
            );

            Mail::to($email)->queue(new CommunityInviteMail($invite));
        }
    }
}
