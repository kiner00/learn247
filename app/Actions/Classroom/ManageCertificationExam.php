<?php

namespace App\Actions\Classroom;

use App\Models\CourseCertification;
use App\Models\Community;
use Illuminate\Support\Facades\Storage;

class ManageCertificationExam
{
    public function store(Community $community, array $data, $coverImageFile = null, ?CourseCertification $existing = null): CourseCertification
    {
        $coverImage = $existing?->cover_image ?? null;

        // Handle cover image upload
        if ($coverImageFile) {
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
                'title'                     => $data['title'],
                'cert_title'                => $data['cert_title'],
                'description'               => $data['description'] ?? null,
                'cover_image'               => $coverImage,
                'pass_score'                => $data['pass_score'],
                'randomize_questions'       => $data['randomize_questions'] ?? false,
                'price'                     => $data['price'] ?? 0,
                'affiliate_commission_rate' => $data['affiliate_commission_rate'] ?? null,
            ]);

            $certification = $existing;
        } else {
            $certification = CourseCertification::create([
                'community_id'              => $community->id,
                'title'                     => $data['title'],
                'cert_title'                => $data['cert_title'],
                'description'               => $data['description'] ?? null,
                'cover_image'               => $coverImage,
                'pass_score'                => $data['pass_score'],
                'randomize_questions'       => $data['randomize_questions'] ?? false,
                'price'                     => $data['price'] ?? 0,
                'affiliate_commission_rate' => $data['affiliate_commission_rate'] ?? null,
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
