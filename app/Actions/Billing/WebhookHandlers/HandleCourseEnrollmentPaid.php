<?php

namespace App\Actions\Billing\WebhookHandlers;

use App\Actions\Affiliate\RecordAffiliateConversion;
use App\Actions\Billing\SendChaChing;
use App\Contracts\WebhookHandler;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class HandleCourseEnrollmentPaid implements WebhookHandler
{
    private ?CourseEnrollment $enrollment = null;

    public function __construct(
        private readonly RecordAffiliateConversion $recordConversion,
        private readonly SendChaChing $sendChaChing,
    ) {}

    public function matches(string $xenditId): bool
    {
        $this->enrollment = CourseEnrollment::with(['course.community', 'affiliate.user'])
            ->where('xendit_id', $xenditId)
            ->first();

        return $this->enrollment !== null;
    }

    public function handle(array $payload, string $eventId, string $status): void
    {
        $enrollment = $this->enrollment;

        $paymentStatus = $this->mapPaymentStatus($status);
        if ($paymentStatus !== Payment::STATUS_PAID) {
            return;
        }

        try {
            $isMonthly  = $enrollment->course?->access_type === \App\Models\Course::ACCESS_PAID_MONTHLY;
            $expiresAt  = $isMonthly
                ? ($enrollment->expires_at?->isFuture() ? $enrollment->expires_at->addMonth() : now()->addMonth())
                : null;

            $enrollment->update([
                'status'     => CourseEnrollment::STATUS_PAID,
                'paid_at'    => now(),
                'expires_at' => $expiresAt,
            ]);

            Log::info('Xendit webhook: course enrollment paid', [
                'enrollment_id' => $enrollment->id,
                'monthly'       => $isMonthly,
            ]);

            // Record affiliate commission for course purchase
            $conversion = $this->recordConversion->executeForCourse($enrollment);

            if ($conversion) {
                $this->sendChaChing->execute(
                    affiliateUser: $enrollment->affiliate->user,
                    creator: $enrollment->course->community->owner,
                    community: $enrollment->course->community,
                    saleAmount: $conversion['sale_amount'],
                    commission: $conversion['commission'],
                    referredBy: $enrollment->affiliate->user->name,
                );
            }
        } catch (\Throwable $e) {
            Log::error('HandleCourseEnrollmentPaid failed', [
                'enrollment_id' => $enrollment->id,
                'error'         => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function mapPaymentStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PAID', 'SETTLED' => Payment::STATUS_PAID,
            'EXPIRED'         => Payment::STATUS_EXPIRED,
            'FAILED'          => Payment::STATUS_FAILED,
            default           => Payment::STATUS_PENDING,
        };
    }
}
