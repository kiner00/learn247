<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Community;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    public function show(string $uuid): Response
    {
        $cert = Certificate::where('uuid', $uuid)
            ->with(['user:id,name,avatar', 'certification:id,title,cert_title,community_id,cover_image'])
            ->firstOrFail();

        $community = Community::select('id', 'name', 'slug', 'avatar')
            ->findOrFail($cert->certification->community_id);

        $certTitle   = $cert->cert_title ?: $cert->certification->cert_title;
        $coverImage  = $cert->certification->cover_image ?: $cert->cover_image ?: null;
        $studentName = $cert->user->name;
        $description = $cert->description ?: "{$studentName} has earned the {$certTitle} certificate.";

        View::share('ogMeta', [
            'title'       => "{$studentName} — {$certTitle}",
            'description' => $description,
            'image'       => $coverImage,
            'url'         => url("/certificates/{$uuid}"),
        ]);

        return Inertia::render('Certificate/Show', [
            'certificate' => [
                'uuid'           => $cert->uuid,
                'issued_at'      => $cert->issued_at->format('F j, Y'),
                'student_name'   => $studentName,
                'student_avatar' => $cert->user->avatar,
                'cert_title'     => $certTitle,
                'exam_title'     => $cert->certification->title,
                'community_name' => $community->name,
                'community_slug' => $community->slug,
                'description'    => $cert->description,
                'cover_image'    => $coverImage,
                'community_logo' => $community->avatar ?: null,
            ],
        ]);
    }
}
