<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Community;
use App\Models\Course;
use App\Models\LessonCompletion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    /** Issue (or retrieve existing) certificate when course is 100% complete. */
    public function issue(Request $request, Community $community, Course $course): RedirectResponse
    {
        $user = $request->user();
        $course->load('modules.lessons');

        $lessonIds = $course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
        $total     = $lessonIds->count();

        abort_if($total === 0, 422, 'This course has no lessons.');

        $completed = LessonCompletion::where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->count();

        abort_unless($completed >= $total, 422, 'You have not completed all lessons yet.');

        $cert = Certificate::firstOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            ['issued_at' => now()]
        );

        return redirect()->route('certificates.show', $cert->uuid);
    }

    /** Public certificate view (shareable link). */
    public function show(string $uuid): Response
    {
        $cert = Certificate::where('uuid', $uuid)
            ->with(['user:id,name,avatar', 'course:id,title,community_id'])
            ->firstOrFail();

        $community = Community::select('id', 'name', 'slug', 'avatar')
            ->findOrFail($cert->course->community_id);

        return Inertia::render('Certificate/Show', [
            'certificate' => [
                'uuid'          => $cert->uuid,
                'issued_at'     => $cert->issued_at->format('F j, Y'),
                'student_name'  => $cert->user->name,
                'student_avatar'=> $cert->user->avatar,
                'course_title'  => $cert->course->title,
                'community_name'=> $community->name,
                'community_slug'=> $community->slug,
            ],
        ]);
    }
}
