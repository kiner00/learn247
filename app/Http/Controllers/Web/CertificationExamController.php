<?php

namespace App\Http\Controllers\Web;

use App\Actions\Classroom\ManageCertificationExam;
use App\Actions\Classroom\SubmitCertificationExam;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseCertification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CertificationExamController extends Controller
{
    public function store(Request $request, Community $community, Course $course, ManageCertificationExam $action): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id || $request->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'title'                             => ['required', 'string', 'max:255'],
            'cert_title'                        => ['required', 'string', 'max:255'],
            'description'                       => ['nullable', 'string', 'max:2000'],
            'cover_image'                       => ['nullable', 'image', 'max:10240'],
            'pass_score'                        => ['required', 'integer', 'min:50', 'max:100'],
            'randomize_questions'               => ['sometimes', 'boolean'],
            'questions'                         => ['required', 'array', 'min:1'],
            'questions.*.question'              => ['required', 'string'],
            'questions.*.type'                  => ['required', 'in:multiple_choice,true_false'],
            'questions.*.options'               => ['required', 'array', 'min:2'],
            'questions.*.options.*.label'       => ['required', 'string'],
            'questions.*.options.*.is_correct'  => ['required', 'boolean'],
        ]);

        $action->store($course, $data, $request->file('cover_image'));

        return back()->with('success', 'Certification exam saved!');
    }

    public function submit(Request $request, Community $community, Course $course, CourseCertification $certification, SubmitCertificationExam $action): RedirectResponse
    {
        $request->validate([
            'answers'   => ['required', 'array'],
            'answers.*' => ['required', 'integer'],
        ]);

        $result = $action->execute($request->user(), $certification, $request->answers);

        return back()->with('cert_exam_result', [
            'score'            => $result['score'],
            'passed'           => $result['passed'],
            'total'            => $result['total'],
            'correct'          => $result['correct'],
            'certificate_uuid' => $result['certificate_uuid'],
        ]);
    }

    public function destroy(Request $request, Community $community, Course $course, CourseCertification $certification, ManageCertificationExam $action): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id || $request->user()->isSuperAdmin(), 403);

        $action->destroy($certification);

        return back()->with('success', 'Certification exam deleted!');
    }
}
