<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Community;
use Illuminate\Http\JsonResponse;

class CertificateController extends Controller
{
    public function show(string $uuid): JsonResponse
    {
        $cert = Certificate::where('uuid', $uuid)
            ->with(['user:id,name,avatar', 'certification:id,title,cert_title,community_id'])
            ->firstOrFail();

        $community = Community::select('id', 'name', 'slug', 'avatar')
            ->findOrFail($cert->certification->community_id);

        return response()->json([
            'certificate' => [
                'uuid' => $cert->uuid,
                'issued_at' => $cert->issued_at->toDateString(),
                'student_name' => $cert->user->name,
                'student_avatar' => $cert->user->avatar,
                'cert_title' => $cert->cert_title ?: $cert->certification->cert_title,
                'exam_title' => $cert->certification->title,
                'community_name' => $community->name,
                'community_slug' => $community->slug,
            ],
        ]);
    }
}
