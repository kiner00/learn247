<?php

namespace App\Http\Controllers\Web;

use App\Actions\Classroom\ManageModule;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CourseModuleController extends Controller
{
    public function store(Request $request, Community $community, Course $course, ManageModule $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);
            $data = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'is_free' => ['sometimes', 'boolean'],
            ]);
            $action->store($course, $data);

            return back()->with('success', 'Module added!');
        } catch (\Throwable $e) {
            Log::error('CourseModuleController@store failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function update(Request $request, Community $community, Course $course, CourseModule $module, ManageModule $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);
            $data = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'is_free' => ['sometimes', 'boolean'],
            ]);
            $action->update($module, $data);

            return back()->with('success', 'Module updated!');
        } catch (\Throwable $e) {
            Log::error('CourseModuleController@update failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function destroy(Request $request, Community $community, Course $course, CourseModule $module, ManageModule $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $action->destroy($module);

            return back()->with('success', 'Module deleted!');
        } catch (\Throwable $e) {
            Log::error('CourseModuleController@destroy failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }
}
