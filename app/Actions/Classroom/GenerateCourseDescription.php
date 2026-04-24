<?php

namespace App\Actions\Classroom;

use App\Ai\Agents\CourseDescriptionWriter;
use App\Exceptions\AiBudgetExceededException;
use App\Models\Community;
use App\Models\Course;
use App\Services\Ai\BudgetGuard;

class GenerateCourseDescription
{
    /**
     * Returns the AI-generated description without persisting it.
     * The client decides whether to keep it (same UX as the cover generator).
     *
     * @throws AiBudgetExceededException if community has hit its spend cap
     * @throws \RuntimeException if the model fails or returns empty text
     */
    public function execute(Community $community, Course $course, ?int $userId): string
    {
        BudgetGuard::assertAllowed(userId: $userId, communityId: $community->id);

        $agent = $this->makeAgent($community, $course);

        $response = $agent->prompt('Write the description now. Output only the description text.');

        $text = trim((string) $response->text);
        $text = preg_replace('/^["\']+|["\']+$/', '', $text);
        $text = preg_replace('/^```(?:\w+)?\s*|\s*```$/m', '', $text);
        $text = trim($text);

        if ($text === '') {
            throw new \RuntimeException('AI returned an empty description. Please try again.');
        }

        return mb_substr($text, 0, 2000);
    }

    /**
     * Agent factory — extracted as a seam so tests can substitute a stub.
     */
    protected function makeAgent(Community $community, Course $course): object
    {
        return new CourseDescriptionWriter(
            community: $community,
            courseTitle: $course->title,
            currentDescription: $course->description,
        );
    }
}
