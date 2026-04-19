<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Workflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    public function store(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        $data = $this->validated($request, $community);

        Workflow::create([
            'community_id' => $community->id,
            'name' => $data['name'],
            'trigger_event' => $data['trigger_event'],
            'trigger_filter' => $this->buildFilter($data),
            'action_type' => $data['action_type'],
            'action_config' => ['tag_id' => $data['tag_id']],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return back()->with('success', 'Workflow created.');
    }

    public function update(Request $request, Community $community, Workflow $workflow): RedirectResponse
    {
        $this->authorize('update', $community);
        abort_unless($workflow->community_id === $community->id, 404);

        $data = $this->validated($request, $community);

        $workflow->update([
            'name' => $data['name'],
            'trigger_event' => $data['trigger_event'],
            'trigger_filter' => $this->buildFilter($data),
            'action_type' => $data['action_type'],
            'action_config' => ['tag_id' => $data['tag_id']],
            'is_active' => $data['is_active'] ?? $workflow->is_active,
        ]);

        return back()->with('success', 'Workflow updated.');
    }

    public function toggle(Community $community, Workflow $workflow): RedirectResponse
    {
        $this->authorize('update', $community);
        abort_unless($workflow->community_id === $community->id, 404);

        $workflow->update(['is_active' => ! $workflow->is_active]);

        return back()->with('success', $workflow->is_active ? 'Workflow activated.' : 'Workflow paused.');
    }

    public function destroy(Community $community, Workflow $workflow): RedirectResponse
    {
        $this->authorize('update', $community);
        abort_unless($workflow->community_id === $community->id, 404);

        $workflow->delete();

        return back()->with('success', 'Workflow deleted.');
    }

    private function validated(Request $request, Community $community): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'trigger_event' => ['required', 'string', Rule::in(Workflow::TRIGGERS)],
            'action_type' => ['required', 'string', Rule::in(Workflow::ACTIONS)],
            'tag_id' => [
                'required',
                'integer',
                Rule::exists('tags', 'id')->where('community_id', $community->id),
            ],
            'course_id' => [
                'nullable',
                'integer',
                Rule::exists('courses', 'id')->where('community_id', $community->id),
            ],
            'membership_type' => ['nullable', 'string', Rule::in(['free', 'paid'])],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function buildFilter(array $data): ?array
    {
        $filter = [];

        if ($data['trigger_event'] === Workflow::TRIGGER_COURSE_ENROLLED && ! empty($data['course_id'])) {
            $filter['course_id'] = (int) $data['course_id'];
        }

        if ($data['trigger_event'] === Workflow::TRIGGER_MEMBER_JOINED && ! empty($data['membership_type'])) {
            $filter['membership_type'] = $data['membership_type'];
        }

        return $filter ?: null;
    }
}
