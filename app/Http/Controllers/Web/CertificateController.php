<?php

namespace App\Http\Controllers\Web;

use App\Actions\Classroom\IssueCertificate;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Community;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    public function issue(Request $request, Community $community, Course $course, IssueCertificate $action): RedirectResponse
    {
        $cert = $action->execute($request->user(), $course);

        return redirect()->route('certificates.show', $cert->uuid);
    }

    public function show(string $uuid): Response
    {
        $cert = Certificate::where('uuid', $uuid)
            ->with(['user:id,name,avatar', 'course:id,title,community_id'])
            ->firstOrFail();

        $community = Community::select('id', 'name', 'slug', 'avatar')->findOrFail($cert->course->community_id);

        return Inertia::render('Certificate/Show', [
            'certificate' => [
                'uuid'           => $cert->uuid,
                'issued_at'      => $cert->issued_at->format('F j, Y'),
                'student_name'   => $cert->user->name,
                'student_avatar' => $cert->user->avatar,
                'course_title'   => $cert->course->title,
                'community_name' => $community->name,
                'community_slug' => $community->slug,
                'cert_title'     => $cert->cert_title,
                'description'    => $cert->description,
                'cover_image'    => $cert->cover_image ? asset('storage/' . $cert->cover_image) : null,
            ],
        ]);
    }
}
