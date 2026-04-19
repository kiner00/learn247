<?php

namespace App\Actions\Classroom;

use App\Models\Community;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use App\Support\InvoiceBuilder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EnrollInCourse
{
    public function __construct(private readonly XenditService $xendit) {}

    /**
     * @return array{enrollment: CourseEnrollment, checkout_url: string}
     */
    public function execute(User $user, Community $community, Course $course, string $successRedirectUrl): array
    {
        $isPaid = in_array($course->access_type, [Course::ACCESS_PAID_ONCE, Course::ACCESS_PAID_MONTHLY]);
        if (! $isPaid) {
            throw ValidationException::withMessages(['course' => 'This course does not require a separate purchase.']);
        }

        // Check for active paid enrollment
        $existing = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', CourseEnrollment::STATUS_PAID)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if ($existing) {
            throw ValidationException::withMessages(['course' => 'You already have access to this course.']);
        }

        try {
            $isMonthly = $course->access_type === Course::ACCESS_PAID_MONTHLY;
            $externalId = "course_{$course->id}_{$user->id}_".time();
            $label = $isMonthly ? "{$course->title} (Monthly)" : $course->title;

            $invoice = $this->xendit->createInvoice(
                InvoiceBuilder::make()
                    ->externalId($externalId)
                    ->amount((float) $course->price)
                    ->currency($community->currency ?? 'PHP')
                    ->description("Course: {$label}")
                    ->customer($user)
                    ->successUrl($successRedirectUrl)
                    ->failureUrl(route('communities.classroom.courses.show', [$community->slug, $course->id]))
                    ->item($label, (float) $course->price, 'Course')
                    ->toArray()
            );

            // Resolve affiliate from the user's active subscription in this community (if any)
            $affiliateId = Subscription::where('user_id', $user->id)
                ->where('community_id', $community->id)
                ->whereNotNull('affiliate_id')
                ->value('affiliate_id');

            // Upsert pending enrollment
            $enrollment = CourseEnrollment::updateOrCreate(
                ['user_id' => $user->id, 'course_id' => $course->id],
                ['affiliate_id' => $affiliateId, 'xendit_id' => $invoice['id'], 'status' => CourseEnrollment::STATUS_PENDING, 'paid_at' => null, 'expires_at' => null],
            );

            return ['enrollment' => $enrollment, 'checkout_url' => $invoice['invoice_url']];
        } catch (\Throwable $e) {
            Log::error('EnrollInCourse failed', [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
