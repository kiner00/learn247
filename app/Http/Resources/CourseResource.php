<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'position'    => $this->position,
            'modules'     => $this->whenLoaded('modules', fn () =>
                $this->modules->map(fn ($module) => [
                    'id'       => $module->id,
                    'title'    => $module->title,
                    'position' => $module->position,
                    'lessons'  => $module->relationLoaded('lessons')
                        ? $module->lessons->map(fn ($lesson) => [
                            'id'        => $lesson->id,
                            'title'     => $lesson->title,
                            'position'  => $lesson->position,
                            'video_url' => $lesson->video_url,
                            'has_quiz'  => $lesson->relationLoaded('quiz') ? (bool) $lesson->quiz : null,
                        ])->values()
                        : [],
                ])->values()
            ),
            'created_at'  => $this->created_at,
        ];
    }
}
