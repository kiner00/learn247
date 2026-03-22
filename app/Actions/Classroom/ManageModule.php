<?php

namespace App\Actions\Classroom;

use App\Models\Course;
use App\Models\CourseModule;

class ManageModule
{
    public function store(Course $course, array $data): CourseModule
    {
        $position = $course->modules()->max('position') + 1;

        return $course->modules()->create(array_merge($data, ['position' => $position]));
    }

    public function update(CourseModule $module, array $data): CourseModule
    {
        $module->update($data);

        return $module;
    }

    public function destroy(CourseModule $module): void
    {
        $module->lessons()->delete();
        $module->delete();
    }
}
