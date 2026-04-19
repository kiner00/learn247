<?php

namespace App\Ai\Tools;

use App\Models\CourseLesson;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchCommunityLessonsTool implements Tool
{
    public function __construct(private int $communityId) {}

    public function description(): string
    {
        return 'Search lessons by keyword in this community. Returns lesson title, content preview, module, and course. Use this to find specific lessons or topics.';
    }

    public function handle(Request $request): string
    {
        $query = trim($request->string('query', ''));

        if (! $query) {
            return 'Please provide a search query.';
        }

        $lessons = CourseLesson::whereHas('module.course', fn ($q) => $q->where('community_id', $this->communityId))
            ->where(fn ($q) => $q->where('title', 'LIKE', "%{$query}%")->orWhere('content', 'LIKE', "%{$query}%"))
            ->with('module:id,title,course_id', 'module.course:id,title')
            ->select('id', 'module_id', 'title', 'content', 'position')
            ->limit(10)
            ->get();

        if ($lessons->isEmpty()) {
            return "No lessons found matching \"{$query}\".";
        }

        $result = $lessons->map(fn ($l) => [
            'lesson' => $l->title,
            'content' => \Illuminate\Support\Str::limit($l->content, 300),
            'module' => $l->module->title,
            'course' => $l->module->course->title,
        ])->values()->toArray();

        return json_encode($result, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Keyword to search for in lesson titles and content.')->required(),
        ];
    }
}
