<?php

namespace App\Ai\Tools;

use App\Models\Course;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetCommunityCoursesTool implements Tool
{
    public function __construct(private int $communityId) {}

    public function description(): string
    {
        return 'Get all courses in this community with their modules and lesson titles. Use this to answer questions about available courses and what they cover.';
    }

    public function handle(Request $request): string
    {
        $courses = Course::where('community_id', $this->communityId)
            ->with(['modules' => fn ($q) => $q->orderBy('position')->select('id', 'course_id', 'title', 'position'),
                'modules.lessons' => fn ($q) => $q->orderBy('position')->select('id', 'module_id', 'title', 'position')])
            ->select('id', 'title', 'description', 'access_type', 'position')
            ->orderBy('position')
            ->get();

        if ($courses->isEmpty()) {
            return 'No courses in this community yet.';
        }

        $result = $courses->map(fn ($c) => [
            'title' => $c->title,
            'description' => \Illuminate\Support\Str::limit($c->description, 200),
            'access' => $c->access_type,
            'modules' => $c->modules->map(fn ($m) => [
                'title' => $m->title,
                'lessons' => $m->lessons->pluck('title')->toArray(),
            ])->toArray(),
        ])->toArray();

        return json_encode($result, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
