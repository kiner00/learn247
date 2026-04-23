<?php

namespace App\Actions\Classroom;

use App\Exceptions\AiBudgetExceededException;
use App\Models\Community;
use App\Models\Course;
use App\Services\Ai\BudgetGuard;
use App\Services\StorageService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Image;
use RuntimeException;

class GenerateCourseCover
{
    public function __construct(private StorageService $storage) {}

    /**
     * Generate an AI cover image for the given course, replace the existing
     * cover_image on disk, and persist the new URL on the model.
     *
     * @throws AiBudgetExceededException if community has hit its spend cap
     * @throws RuntimeException if the image provider fails
     */
    public function execute(Community $community, Course $course, ?int $userId, ?string $extraPrompt = null): Course
    {
        BudgetGuard::assertAllowed(userId: $userId, communityId: $community->id);

        $prompt = $this->buildPrompt($community, $course, $extraPrompt);

        $response = Image::of($prompt)->size('16:9')->generate();
        $image = $response->firstImage();

        $filename = 'course-covers/'.$community->id.'/'.Str::uuid().'.png';
        Storage::put($filename, base64_decode($image->image));
        $url = Storage::url($filename);

        // Delete the old cover (best-effort; don't leak if it was a default).
        $this->storage->delete($course->cover_image);

        $course->update(['cover_image' => $url]);

        return $course->refresh();
    }

    private function buildPrompt(Community $community, Course $course, ?string $extraPrompt): string
    {
        $brand = $community->brand_context ?? [];

        $parts = [
            "A professional 16:9 course cover banner for an online course titled \"{$course->title}\".",
        ];

        if ($course->description) {
            $parts[] = 'Course covers: '.Str::limit($course->description, 240, '').'.';
        }

        $parts[] = 'Clean, modern design with bold typography, clear focal subject, and space for the title. Suitable for a classroom thumbnail.';

        if ($extraPrompt) {
            $parts[] = trim($extraPrompt);
        }

        if (! empty($brand['visual_style'])) {
            $parts[] = "Visual style: {$brand['visual_style']}.";
        }
        if (! empty($brand['brand_personality'])) {
            $parts[] = "Brand personality: {$brand['brand_personality']}.";
        }
        if (! empty($brand['color_primary'])) {
            $parts[] = "Primary color: {$brand['color_primary']}.";
        }
        if (! empty($brand['color_secondary'])) {
            $parts[] = "Secondary color: {$brand['color_secondary']}.";
        }
        if (! empty($brand['color_accent'])) {
            $parts[] = "Accent color: {$brand['color_accent']}.";
        }
        if (! empty($brand['target_audience'])) {
            $parts[] = "Target audience: {$brand['target_audience']}.";
        }

        return implode(' ', $parts);
    }
}
