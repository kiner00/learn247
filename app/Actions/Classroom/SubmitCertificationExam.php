<?php

namespace App\Actions\Classroom;

use App\Models\Certificate;
use App\Models\CertificationAttempt;
use App\Models\CourseCertification;
use App\Models\User;

class SubmitCertificationExam
{
    public function execute(User $user, CourseCertification $certification, array $answers): array
    {
        $certification->load('questions.options');

        $questions = $certification->questions;
        $total     = $questions->count();
        $correct   = 0;

        foreach ($questions as $question) {
            $submittedOptionId = $answers[$question->id] ?? null;
            if (! $submittedOptionId) {
                continue;
            }

            $correctOption = $question->options->firstWhere('is_correct', true);
            if ($correctOption && (int) $submittedOptionId === $correctOption->id) {
                $correct++;
            }
        }

        $score  = $total > 0 ? (int) round(($correct / $total) * 100) : 0;
        $passed = $score >= $certification->pass_score;

        $attempt = CertificationAttempt::create([
            'certification_id' => $certification->id,
            'user_id'          => $user->id,
            'answers'          => $answers,
            'score'            => $score,
            'passed'           => $passed,
            'completed_at'     => now(),
        ]);

        $certificateUuid = null;

        if ($passed) {
            $certificate = Certificate::firstOrCreate(
                [
                    'user_id'          => $user->id,
                    'certification_id' => $certification->id,
                ],
                [
                    'issued_at'   => now(),
                    'cert_title'  => $certification->cert_title,
                    'description' => $certification->description,
                    'cover_image' => $certification->cover_image,
                ]
            );

            // Update cert fields if this is a re-issue after exam changes
            if (! $certificate->wasRecentlyCreated) {
                $certificate->update([
                    'cert_title'  => $certification->cert_title,
                    'description' => $certification->description,
                    'cover_image' => $certification->cover_image,
                ]);
            }

            $certificateUuid = $certificate->uuid;
        }

        return [
            'score'            => $score,
            'passed'           => $passed,
            'total'            => $total,
            'correct'          => $correct,
            'certificate_uuid' => $certificateUuid,
        ];
    }
}
