<?php

namespace App\Ai\Tools;

use App\Models\CommunityMember;
use App\Models\CourseLesson;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchLessonsTool implements Tool
{
    public function __construct(private int $userId) {}

    public function description(): string
    {
        return "Search for lessons by keyword across all communities the user belongs to. Returns lesson title, module, course, and community.";
    }

    public function handle(Request $request): string
    {
        $query = trim($request->string('query', ''));

        if (! $query) {
            return 'Please provide a search query.';
        }

        $communityIds = CommunityMember::where('user_id', $this->userId)->pluck('community_id');

        $lessons = CourseLesson::whereHas('module.course', fn ($q) => $q->whereIn('community_id', $communityIds))
            ->where('title', 'LIKE', "%{$query}%")
            ->with('module:id,title,course_id', 'module.course:id,title,community_id', 'module.course.community:id,name')
            ->select('id', 'module_id', 'title', 'position')
            ->limit(10)
            ->get();

        if ($lessons->isEmpty()) {
            return "No lessons found matching \"{$query}\".";
        }

        $result = $lessons->map(fn ($l) => [
            'lesson'    => $l->title,
            'module'    => $l->module->title,
            'course'    => $l->module->course->title,
            'community' => $l->module->course->community->name,
        ])->values()->toArray();

        return json_encode($result, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Keyword to search for in lesson titles.')->required(),
        ];
    }
}
