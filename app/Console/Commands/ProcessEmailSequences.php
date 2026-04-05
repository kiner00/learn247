<?php

namespace App\Console\Commands;

use App\Jobs\SendSequenceStepEmail;
use App\Models\EmailSequenceEnrollment;
use Illuminate\Console\Command;

class ProcessEmailSequences extends Command
{
    protected $signature = 'email-sequences:process';

    protected $description = 'Process pending email sequence steps and dispatch send jobs';

    public function handle(): int
    {
        $enrollments = EmailSequenceEnrollment::where('status', EmailSequenceEnrollment::STATUS_ACTIVE)
            ->whereNotNull('next_send_at')
            ->where('next_send_at', '<=', now())
            ->whereNotNull('current_step_id')
            ->with(['sequence', 'currentStep', 'member.user'])
            ->limit(100) // Process in manageable batches
            ->get();

        if ($enrollments->isEmpty()) {
            $this->info('No pending sequence steps to process.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach ($enrollments as $enrollment) {
            // Skip if sequence is no longer active
            if ($enrollment->sequence->status !== 'active') {
                continue;
            }

            SendSequenceStepEmail::dispatch($enrollment);
            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} sequence step email(s).");

        return self::SUCCESS;
    }
}
