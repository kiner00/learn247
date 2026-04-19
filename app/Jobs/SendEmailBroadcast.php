<?php

namespace App\Jobs;

use App\Models\CommunityMember;
use App\Models\EmailBroadcast;
use App\Models\EmailUnsubscribe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmailBroadcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly EmailBroadcast $broadcast
    ) {}

    public function handle(): void
    {
        $broadcast = $this->broadcast;
        $community = $broadcast->community;

        // Build recipient query
        $query = CommunityMember::where('community_id', $community->id)
            ->where('is_blocked', false)
            ->whereHas('user', fn ($q) => $q->whereNotNull('email'));

        // Exclude unsubscribed users
        $unsubscribedUserIds = EmailUnsubscribe::where('community_id', $community->id)
            ->pluck('user_id');

        if ($unsubscribedUserIds->isNotEmpty()) {
            $query->whereHas('user', fn ($q) => $q->whereNotIn('id', $unsubscribedUserIds));
        }

        // Include tags (OR logic: member has at least one of the selected tags)
        if (! empty($broadcast->filter_tags)) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $broadcast->filter_tags));
        }

        // Exclude tags (member must NOT have any of the excluded tags)
        if (! empty($broadcast->filter_exclude_tags)) {
            $query->whereDoesntHave('tags', fn ($q) => $q->whereIn('tags.id', $broadcast->filter_exclude_tags));
        }

        // Registered days filter (only members registered over X days ago)
        if ($broadcast->filter_registered_days !== null && $broadcast->filter_registered_days > 0) {
            $query->where('created_at', '<=', now()->subDays($broadcast->filter_registered_days));
        }

        // Membership type filtering
        if ($broadcast->filter_membership_type) {
            $query->where('membership_type', $broadcast->filter_membership_type);
        }

        $memberIds = $query->pluck('id')->toArray();

        $broadcast->update([
            'status' => EmailBroadcast::STATUS_SENDING,
            'total_recipients' => count($memberIds),
        ]);

        if (empty($memberIds)) {
            $broadcast->update([
                'status' => EmailBroadcast::STATUS_SENT,
                'sent_at' => now(),
            ]);
            $broadcast->campaign->update(['status' => 'sent']);

            return;
        }

        // Chunk into batches of 50 and dispatch child jobs
        foreach (array_chunk($memberIds, 50) as $chunk) {
            SendEmailBroadcastBatch::dispatch($broadcast, $chunk);
        }
    }
}
