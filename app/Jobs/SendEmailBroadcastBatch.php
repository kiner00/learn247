<?php

namespace App\Jobs;

use App\Models\CommunityMember;
use App\Models\EmailBroadcast;
use App\Models\EmailSend;
use App\Services\Email\EmailProviderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class SendEmailBroadcastBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly EmailBroadcast $broadcast,
        public readonly array $memberIds,
    ) {}

    public function handle(): void
    {
        $broadcast = $this->broadcast;
        $community = $broadcast->community;

        try {
            $provider = EmailProviderFactory::make($community);
        } catch (\RuntimeException $e) {
            Log::error("SendEmailBroadcastBatch: {$e->getMessage()}", [
                'broadcast_id' => $broadcast->id,
            ]);

            return;
        }

        $members = CommunityMember::whereIn('id', $this->memberIds)
            ->with('user:id,name,email')
            ->get();

        $fromEmail = $broadcast->from_email ?? $community->resend_from_email ?? 'onboarding@resend.dev';
        $fromName = $broadcast->from_name ?? $community->resend_from_name ?? $community->name;

        $emailPayloads = [];
        $sendRecords = [];

        foreach ($members as $member) {
            if (! $member->user?->email) {
                continue;
            }

            $unsubscribeUrl = URL::signedRoute('email.unsubscribe', [
                'community' => $community->slug,
                'member' => $member->id,
            ]);

            $html = $this->interpolate($broadcast->html_body, [
                'user_name' => $member->user->name ?? 'Member',
                'user_email' => $member->user->email,
                'community_name' => $community->name,
                'unsubscribe_url' => $unsubscribeUrl,
            ]);

            // Append unsubscribe footer
            $html .= '<p style="font-size:12px;color:#999;margin-top:32px;text-align:center;">'
                    .'<a href="'.e($unsubscribeUrl).'" style="color:#999;">Unsubscribe</a></p>';

            $emailPayloads[] = [
                'from' => "{$fromName} <{$fromEmail}>",
                'to' => [$member->user->email],
                'subject' => $broadcast->subject,
                'html' => $html,
                'reply_to' => $broadcast->reply_to ? [$broadcast->reply_to] : ($community->resend_reply_to ? [$community->resend_reply_to] : []),
            ];

            $sendRecords[] = EmailSend::create([
                'broadcast_id' => $broadcast->id,
                'community_id' => $community->id,
                'community_member_id' => $member->id,
                'status' => 'queued',
            ]);
        }

        if (empty($emailPayloads)) {
            return;
        }

        // Send in chunks of 100 (batch API limit)
        foreach (array_chunk($emailPayloads, 100) as $batchIndex => $batch) {
            try {
                $results = $provider->sendBatch($community, $batch);

                // Map returned email IDs to send records
                $offset = $batchIndex * 100;
                foreach ($results as $j => $email) {
                    $idx = $offset + $j;
                    if (isset($sendRecords[$idx])) {
                        $sendRecords[$idx]->update([
                            'resend_email_id' => $email['id'] ?? null,
                            'status' => 'sent',
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('SendEmailBroadcastBatch: batch send failed', [
                    'broadcast_id' => $broadcast->id,
                    'error' => $e->getMessage(),
                ]);

                // Mark remaining as failed
                $offset = $batchIndex * 100;
                for ($j = 0; $j < count($batch); $j++) {
                    $idx = $offset + $j;
                    if (isset($sendRecords[$idx])) {
                        $sendRecords[$idx]->update([
                            'status' => 'failed',
                            'failed_reason' => $e->getMessage(),
                        ]);
                    }
                }

                $broadcast->increment('total_failed', count($batch));
            }
        }

        // Update broadcast sent counter atomically
        $sentCount = collect($sendRecords)->where('status', 'sent')->count();
        $broadcast->increment('total_sent', $sentCount);

        // Check if all batches are complete
        $broadcast->refresh();
        if ($broadcast->total_sent + $broadcast->total_failed >= $broadcast->total_recipients) {
            $broadcast->update([
                'status' => EmailBroadcast::STATUS_SENT,
                'sent_at' => now(),
            ]);
            $broadcast->campaign->update(['status' => 'sent']);
        }
    }

    private function interpolate(string $html, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $html = str_replace('{{'.$key.'}}', e($value), $html);
        }

        return $html;
    }
}
