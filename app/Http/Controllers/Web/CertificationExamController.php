<?php

namespace App\Http\Controllers\Web;

use App\Actions\Billing\CheckoutCertification;
use App\Actions\Classroom\ManageCertificationExam;
use App\Actions\Classroom\SubmitCertificationExam;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificationAttempt;
use App\Models\CertificationPurchase;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CourseCertification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class CertificationExamController extends Controller
{
    /**
     * Certifications tab page — list all certifications for this community.
     */
    public function index(Request $request, Community $community): Response
    {
        try {
            $userId    = auth()->id();
            $canManage = $request->user()?->can('manage', $community) ?? false;
            $community->loadCount('members');

            $certifications = $community->certifications()
                ->with('questions.options')
                ->withCount('questions')
                ->get()
                ->map(function ($cert) use ($canManage) {
                    return [
                        'id'                        => $cert->id,
                        'title'                     => $cert->title,
                        'cert_title'                => $cert->cert_title,
                        'description'               => $cert->description,
                        'cover_image'               => $cert->cover_image ?: null,
                        'pass_score'                => $cert->pass_score,
                        'randomize_questions'       => $cert->randomize_questions,
                        'price'                     => (float) ($cert->price ?? 0),
                        'affiliate_commission_rate' => $cert->affiliate_commission_rate,
                        'questions_count'           => $cert->questions_count,
                        'questions'           => $cert->questions->map(fn ($q) => [
                            'id'       => $q->id,
                            'question' => $q->question,
                            'type'     => $q->type,
                            'options'  => $q->options->map(fn ($o) => [
                                'id'         => $o->id,
                                'label'      => $o->label,
                                'is_correct' => $canManage ? $o->is_correct : false,
                            ])->values(),
                        ])->values(),
                    ];
                });

            // Fetch user's best attempts for each certification
            $attempts = [];
            if ($userId) {
                $attempts = CertificationAttempt::where('user_id', $userId)
                    ->whereIn('certification_id', $certifications->pluck('id'))
                    ->get()
                    ->groupBy('certification_id')
                    ->map(fn ($group) => $group->sortByDesc('score')->first())
                    ->map(fn ($attempt) => [
                        'score'        => $attempt->score,
                        'passed'       => $attempt->passed,
                        'completed_at' => $attempt->completed_at?->format('F j, Y'),
                    ])
                    ->toArray();
            }

            // Fetch user's paid certification purchases
            $purchases = [];
            if ($userId) {
                $purchases = CertificationPurchase::where('user_id', $userId)
                    ->where('status', CertificationPurchase::STATUS_PAID)
                    ->whereIn('certification_id', $certifications->pluck('id'))
                    ->pluck('certification_id')
                    ->flip()
                    ->map(fn () => true)
                    ->toArray();
            }

            // Fetch user's certificates
            $userCertificates = [];
            if ($userId) {
                $userCertificates = Certificate::where('user_id', $userId)
                    ->whereIn('certification_id', $certifications->pluck('id'))
                    ->get()
                    ->keyBy('certification_id')
                    ->map(fn ($c) => ['uuid' => $c->uuid])
                    ->toArray();
            }

            // For owner: all issued certificates
            $issuedCertificates = collect();
            if ($canManage) {
                $issuedCertificates = Certificate::whereIn('certification_id', $certifications->pluck('id'))
                    ->with(['user:id,name,avatar', 'certification:id,title,cert_title'])
                    ->latest('issued_at')
                    ->get()
                    ->map(fn ($c) => [
                        'uuid'           => $c->uuid,
                        'issued_at'      => $c->issued_at?->format('M j, Y'),
                        'cert_title'     => $c->cert_title ?: $c->certification?->cert_title,
                        'exam_title'     => $c->certification?->title,
                        'student_name'   => $c->user?->name,
                        'student_avatar' => $c->user?->avatar,
                    ]);
            }

            return Inertia::render('Communities/Certifications/Index', [
                'community'          => $community,
                'certifications'     => $certifications,
                'attempts'           => $attempts,
                'userCertificates'   => $userCertificates,
                'issuedCertificates' => $issuedCertificates,
                'canManage'          => $canManage,
                'purchases'          => $purchases,
            ]);
        } catch (\Throwable $e) {
            Log::error('CertificationExamController@index failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function store(Request $request, Community $community, ManageCertificationExam $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $data = $request->validate([
                'title'                             => ['required', 'string', 'max:255'],
                'cert_title'                        => ['required', 'string', 'max:255'],
                'description'                       => ['nullable', 'string', 'max:2000'],
                'cover_image'                       => ['nullable', 'image', 'max:10240'],
                'pass_score'                        => ['required', 'integer', 'min:50', 'max:100'],
                'randomize_questions'               => ['sometimes', 'boolean'],
                'price'                             => ['nullable', 'numeric', 'min:0'],
                'affiliate_commission_rate'         => ['nullable', 'integer', 'min:0', 'max:100'],
                'questions'                         => ['required', 'array', 'min:1'],
                'questions.*.question'              => ['required', 'string'],
                'questions.*.type'                  => ['required', 'in:multiple_choice,true_false'],
                'questions.*.options'               => ['required', 'array', 'min:2'],
                'questions.*.options.*.label'       => ['required', 'string'],
                'questions.*.options.*.is_correct'  => ['required', 'boolean'],
            ]);

            $action->store($community, $data, $request->file('cover_image'));

            return back()->with('success', 'Certification exam saved!');
        } catch (\Throwable $e) {
            Log::error('CertificationExamController@store failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function update(Request $request, Community $community, CourseCertification $certification, ManageCertificationExam $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);
            abort_unless($certification->community_id === $community->id, 404);

            $data = $request->validate([
                'title'                             => ['required', 'string', 'max:255'],
                'cert_title'                        => ['required', 'string', 'max:255'],
                'description'                       => ['nullable', 'string', 'max:2000'],
                'cover_image'                       => ['nullable', 'image', 'max:10240'],
                'pass_score'                        => ['required', 'integer', 'min:50', 'max:100'],
                'randomize_questions'               => ['sometimes', 'boolean'],
                'price'                             => ['nullable', 'numeric', 'min:0'],
                'affiliate_commission_rate'         => ['nullable', 'integer', 'min:0', 'max:100'],
                'questions'                         => ['required', 'array', 'min:1'],
                'questions.*.question'              => ['required', 'string'],
                'questions.*.type'                  => ['required', 'in:multiple_choice,true_false'],
                'questions.*.options'               => ['required', 'array', 'min:2'],
                'questions.*.options.*.label'       => ['required', 'string'],
                'questions.*.options.*.is_correct'  => ['required', 'boolean'],
            ]);

            $action->store($community, $data, $request->file('cover_image'), $certification);

            return back()->with('success', 'Certification exam updated!');
        } catch (\Throwable $e) {
            Log::error('CertificationExamController@update failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function checkout(Request $request, Community $community, CourseCertification $certification, CheckoutCertification $action): mixed
    {
        try {
            abort_unless($certification->community_id === $community->id, 404);

            $successUrl = route('communities.certifications', [$community->slug]);
            $result = $action->execute($request->user(), $community, $certification, $successUrl);

            return Inertia::location($result['checkout_url']);
        } catch (\Throwable $e) {
            Log::error('CertificationExamController@checkout failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function submit(Request $request, Community $community, CourseCertification $certification, SubmitCertificationExam $action): RedirectResponse
    {
        try {
            abort_unless($certification->community_id === $community->id, 404);

            // Gate: paid certifications require purchase
            if ($certification->price > 0) {
                $hasPurchased = CertificationPurchase::where('user_id', $request->user()->id)
                    ->where('certification_id', $certification->id)
                    ->where('status', CertificationPurchase::STATUS_PAID)
                    ->exists();
                abort_unless($hasPurchased, 403, 'You must purchase this certification before taking the exam.');
            }

            $request->validate([
                'answers'   => ['required', 'array'],
                'answers.*' => ['required', 'integer'],
            ]);

            $result = $action->execute($request->user(), $certification, $request->answers);

            return back()->with('cert_exam_result', [
                'certification_id' => $certification->id,
                'score'            => $result['score'],
                'passed'           => $result['passed'],
                'total'            => $result['total'],
                'correct'          => $result['correct'],
                'certificate_uuid' => $result['certificate_uuid'],
            ]);
        } catch (\Throwable $e) {
            Log::error('CertificationExamController@submit failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function destroy(Request $request, Community $community, CourseCertification $certification, ManageCertificationExam $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);
            abort_unless($certification->community_id === $community->id, 404);

            $action->destroy($certification);

            return back()->with('success', 'Certification exam deleted!');
        } catch (\Throwable $e) {
            Log::error('CertificationExamController@destroy failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }
}
