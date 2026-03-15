<?php

namespace App\Http\Controllers\Api;

use App\Actions\Classroom\IssueCertificate;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Community;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function issue(Request $request, Community $community, Course $course, IssueCertificate $action): JsonResponse
    {
        $cert = $action->execute($request->user(), $course);

        return response()->json([
            'message' => 'Certificate issued.',
            'certificate' => ['uuid' => $cert->uuid],
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $cert = Certificate::where('uuid', $uuid)
            ->with(['user:id,name,avatar', 'course:id,title,community_id'])
            ->firstOrFail();

        $community = Community::select('id', 'name', 'slug', 'avatar')->findOrFail($cert->course->community_id);

        return response()->json([
            'certificate' => [
                'uuid'           => $cert->uuid,
                'issued_at'      => $cert->issued_at->toDateString(),
                'student_name'   => $cert->user->name,
                'student_avatar' => $cert->user->avatar,
                'course_title'   => $cert->course->title,
                'community_name' => $community->name,
                'community_slug' => $community->slug,
            ],
        ]);
    }
}
