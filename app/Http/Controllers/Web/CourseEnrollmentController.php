<?php

namespace App\Http\Controllers\Web;

use App\Actions\Classroom\EnrollInCourse;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CourseEnrollmentController extends Controller
{
    public function checkout(Request $request, Community $community, Course $course, EnrollInCourse $action): mixed
    {
        $successRedirectUrl = route('communities.classroom.courses.show', [$community->slug, $course->id]);

        $result = $action->execute($request->user(), $community, $course, $successRedirectUrl);

        return Inertia::location($result['checkout_url']);
    }
}
