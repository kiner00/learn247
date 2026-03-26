<?php

namespace App\Actions\Classroom;

use App\Models\CourseCertification;
use App\Models\Course;
use Illuminate\Support\Facades\Storage;

class ManageCertificationExam
{
    public function store(Course $course, array $data, $coverImageFile = null): CourseCertification
    {
        // Delete existing certification (cascade deletes questions/options/attempts)
        $existing = $course->certification;

        $coverImage = $existing?->cover_image ?? null;

        // Handle cover image upload
        if ($coverImageFile) {
            // Delete old cover image if present
            if ($coverImage) {
                Storage::disk('public')->delete($coverImage);
            }
            $coverImage = $coverImageFile->store('certification-covers', 'public');
        } elseif (isset($data['remove_cover_image']) && $data['remove_cover_image']) {
            if ($coverImage) {
                Storage::disk('public')->delete($coverImage);
            }
            $coverImage = null;
        }

        if ($existing) {
            $existing->questions()->each(fn ($q) => $q->options()->delete());
            $existing->questions()->delete();

            $existing->update([
                'title'               => $data['title'],
                'cert_title'          => $data['cert_title'],
                'description'         => $data['description'] ?? null,
                'cover_image'         => $coverImage,
                'pass_score'          => $data['pass_score'],
                'randomize_questions' => $data['randomize_questions'] ?? false,
            ]);

            $certification = $existing;
        } else {
            $certification = CourseCertification::create([
                'course_id'           => $course->id,
                'title'               => $data['title'],
                'cert_title'          => $data['cert_title'],
                'description'         => $data['description'] ?? null,
                'cover_image'         => $coverImage,
                'pass_score'          => $data['pass_score'],
                'randomize_questions' => $data['randomize_questions'] ?? false,
            ]);
        }

        // Create questions and options
        foreach ($data['questions'] as $position => $qData) {
            $question = $certification->questions()->create([
                'question' => $qData['question'],
                'type'     => $qData['type'] ?? 'multiple_choice',
                'position' => $position,
            ]);

            foreach ($qData['options'] as $optData) {
                $question->options()->create([
                    'label'      => $optData['label'],
                    'is_correct' => $optData['is_correct'],
                ]);
            }
        }

        return $certification->fresh(['questions.options']);
    }

    public function destroy(CourseCertification $certification): void
    {
        if ($certification->cover_image) {
            Storage::disk('public')->delete($certification->cover_image);
        }

        $certification->delete();
    }
}
