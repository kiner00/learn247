<?php

namespace App\Actions\Classroom;

use App\Models\Community;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Validation\ValidationException;

class EnrollInCourse
{
    public function __construct(private readonly XenditService $xendit) {}

    /**
     * @return array{enrollment: CourseEnrollment, checkout_url: string}
     */
    public function execute(User $user, Community $community, Course $course, string $successRedirectUrl): array
    {
        if ($course->access_type !== Course::ACCESS_PAID_ONCE) {
            throw ValidationException::withMessages(['course' => 'This course does not require a separate purchase.']);
        }

        $existing = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', CourseEnrollment::STATUS_PAID)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages(['course' => 'You already have access to this course.']);
        }

        $externalId = "course_{$course->id}_{$user->id}_" . time();

        $invoice = $this->xendit->createInvoice([
            'external_id' => $externalId,
            'amount'      => (float) $course->price,
            'currency'    => $community->currency ?? 'PHP',
            'description' => "Course: {$course->title}",
            'customer'    => ['given_names' => $user->name, 'email' => $user->email],
            'customer_notification_preference' => [
                'invoice_created' => ['email'],
                'invoice_paid'    => ['email'],
            ],
            'success_redirect_url' => $successRedirectUrl,
            'failure_redirect_url' => route('communities.classroom.courses.show', [$community->slug, $course->id]),
            'items' => [[
                'name'     => $course->title,
                'quantity' => 1,
                'price'    => (float) $course->price,
                'category' => 'Course',
            ]],
        ]);

        // Upsert pending enrollment (replace any previous pending one)
        $enrollment = CourseEnrollment::updateOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            ['xendit_id' => $invoice['id'], 'status' => CourseEnrollment::STATUS_PENDING, 'paid_at' => null],
        );

        return ['enrollment' => $enrollment, 'checkout_url' => $invoice['invoice_url']];
    }
}
