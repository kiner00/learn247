<?php

namespace App\Jobs;

use App\Models\EmailSend;
use App\Models\EmailSequenceEnrollment;
use App\Models\EmailSequenceStep;
use App\Models\EmailUnsubscribe;
use App\Services\Community\ResendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class SendSequenceStepEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly EmailSequenceEnrollment $enrollment,
    ) {}

    public function handle(): void
    {
        $enrollment = $this->enrollment;

        // Re-check status
        if ($enrollment->status !== EmailSequenceEnrollment::STATUS_ACTIVE) {
            return;
        }

        $step      = $enrollment->currentStep;
        $sequence  = $enrollment->sequence;
        $member    = $enrollment->member;
        $community = $sequence->community;

        if (! $step || ! $member || ! $community) {
            return;
        }

        // Check if member is unsubscribed
        $isUnsubscribed = EmailUnsubscribe::where('community_id', $community->id)
            ->where('user_id', $member->user_id)
            ->exists();

        if ($isUnsubscribed) {
            $enrollment->update(['status' => EmailSequenceEnrollment::STATUS_CANCELLED]);

            return;
        }

        $user = $member->user;
        if (! $user?->email) {
            return;
        }

        // Send the email
        try {
            $resend = new ResendService($community);
        } catch (\RuntimeException $e) {
            Log::error("SendSequenceStepEmail: {$e->getMessage()}", ['enrollment_id' => $enrollment->id]);

            return;
        }

        $unsubscribeUrl = URL::signedRoute('email.unsubscribe', [
            'community' => $community->slug,
            'member'    => $member->id,
        ]);

        $html = $this->interpolate($step->html_body, [
            'user_name'       => $user->name ?? 'Member',
            'user_email'      => $user->email,
            'community_name'  => $community->name,
            'unsubscribe_url' => $unsubscribeUrl,
        ]);

        $html .= '<p style="font-size:12px;color:#999;margin-top:32px;text-align:center;">'
                . '<a href="' . e($unsubscribeUrl) . '" style="color:#999;">Unsubscribe</a></p>';

        $fromEmail = $step->from_email ?? $community->resend_from_email ?? 'onboarding@resend.dev';
        $fromName  = $step->from_name ?? $community->resend_from_name ?? $community->name;

        try {
            $result = $resend->sendEmail([
                'from'    => "{$fromName} <{$fromEmail}>",
                'to'      => [$user->email],
                'subject' => $step->subject,
                'html'    => $html,
            ]);

            // Track the send
            EmailSend::create([
                'broadcast_id'        => null,
                'community_id'        => $community->id,
                'community_member_id' => $member->id,
                'resend_email_id'     => $result->id ?? null,
                'status'              => 'sent',
            ]);
        } catch (\Exception $e) {
            Log::error("SendSequenceStepEmail: send failed", [
                'enrollment_id' => $enrollment->id,
                'step_id'       => $step->id,
                'error'         => $e->getMessage(),
            ]);

            EmailSend::create([
                'broadcast_id'        => null,
                'community_id'        => $community->id,
                'community_member_id' => $member->id,
                'status'              => 'failed',
                'failed_reason'       => $e->getMessage(),
            ]);

            return;
        }

        // Advance to next step
        $nextStep = EmailSequenceStep::where('sequence_id', $sequence->id)
            ->where('position', '>', $step->position)
            ->orderBy('position')
            ->first();

        if ($nextStep) {
            $enrollment->update([
                'current_step_id' => $nextStep->id,
                'steps_completed' => $enrollment->steps_completed + 1,
                'next_send_at'    => now()->addHours($nextStep->delay_hours),
            ]);
        } else {
            // Sequence complete
            $enrollment->update([
                'current_step_id' => null,
                'steps_completed' => $enrollment->steps_completed + 1,
                'status'          => EmailSequenceEnrollment::STATUS_COMPLETED,
                'completed_at'    => now(),
                'next_send_at'    => null,
            ]);
        }
    }

    private function interpolate(string $html, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $html = str_replace('{{' . $key . '}}', e($value), $html);
        }

        return $html;
    }
}
