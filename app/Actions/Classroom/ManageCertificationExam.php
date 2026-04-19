<?php

namespace App\Actions\Classroom;

use App\Contracts\FileStorage;
use App\Models\Community;
use App\Models\CourseCertification;

class ManageCertificationExam
{
    public function __construct(private FileStorage $storage) {}

    public function store(Community $community, array $data, $coverImageFile = null, ?CourseCertification $existing = null): CourseCertification
    {
        $coverImage = $existing?->cover_image ?? null;

        if ($coverImageFile) {
            $this->storage->delete($coverImage);
            $coverImage = $this->storage->upload($coverImageFile, 'certification-covers');
        } elseif (isset($data['remove_cover_image']) && $data['remove_cover_image']) {
            $this->storage->delete($coverImage);
            $coverImage = null;
        }

        if ($existing) {
            $existing->questions()->each(fn ($q) => $q->options()->delete());
            $existing->questions()->delete();

            $existing->update([
                'title' => $data['title'],
                'cert_title' => $data['cert_title'],
                'description' => $data['description'] ?? null,
                'cover_image' => $coverImage,
                'pass_score' => $data['pass_score'],
                'randomize_questions' => $data['randomize_questions'] ?? false,
                'price' => $data['price'] ?? 0,
                'affiliate_commission_rate' => $data['affiliate_commission_rate'] ?? null,
            ]);

            $certification = $existing;
        } else {
            $certification = CourseCertification::create([
                'community_id' => $community->id,
                'title' => $data['title'],
                'cert_title' => $data['cert_title'],
                'description' => $data['description'] ?? null,
                'cover_image' => $coverImage,
                'pass_score' => $data['pass_score'],
                'randomize_questions' => $data['randomize_questions'] ?? false,
                'price' => $data['price'] ?? 0,
                'affiliate_commission_rate' => $data['affiliate_commission_rate'] ?? null,
            ]);
        }

        foreach ($data['questions'] as $position => $qData) {
            $question = $certification->questions()->create([
                'question' => $qData['question'],
                'type' => $qData['type'] ?? 'multiple_choice',
                'position' => $position,
            ]);

            foreach ($qData['options'] as $optData) {
                $question->options()->create([
                    'label' => $optData['label'],
                    'is_correct' => $optData['is_correct'],
                ]);
            }
        }

        return $certification->fresh(['questions.options']);
    }

    public function destroy(CourseCertification $certification): void
    {
        $this->storage->delete($certification->cover_image);
        $certification->delete();
    }
}
