<?php

namespace App\Http\Controllers\Web;

use App\Contracts\FileStorage;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Curzzo;
use App\Services\Community\PlanLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CurzzoController extends Controller
{
    public function index(Community $community): Response
    {
        $this->authorize('update', $community);

        $curzzos = $community->curzzos()->get();

        $modelTiers = collect(config('curzzos.tiers'))->map(fn ($tier, $key) => [
            'value'       => $key,
            'label'       => $tier['label'],
            'description' => $tier['description'],
        ])->values();

        return Inertia::render('Communities/Settings/Curzzos', [
            'community'  => $community,
            'isPro'      => auth()->user()->creatorPlan() === 'pro',
            'curzzos'    => $curzzos,
            'modelTiers' => $modelTiers,
        ]);
    }

    public function store(Request $request, Community $community, PlanLimitService $planLimit, FileStorage $storage): RedirectResponse
    {
        $this->authorize('update', $community);

        if (! $planLimit->canCreateCurzzo($request->user(), $community)) {
            return back()->withErrors([
                'plan' => 'Curzzos require a Pro plan (max 5 per community).',
            ]);
        }

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:500'],
            'instructions' => ['required', 'string', 'max:5000'],
            'personality'  => ['nullable', 'array'],
            'personality.tone'           => ['nullable', 'string', 'in:friendly,professional,casual,formal'],
            'personality.expertise'      => ['nullable', 'string', 'max:200'],
            'personality.response_style' => ['nullable', 'string', 'in:concise,detailed,conversational'],
            'avatar'                     => ['nullable', 'image', 'max:2048'],
            'model_tier'                 => ['sometimes', 'string', Rule::in(array_keys(config('curzzos.tiers')))],
            'price'                      => ['nullable', 'numeric', 'min:0'],
            'currency'                   => ['nullable', 'string', 'in:PHP,USD'],
            'billing_type'               => ['nullable', 'string', 'in:one_time,monthly'],
            'affiliate_commission_rate'  => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $storage->upload($request->file('avatar'), 'curzzo-avatars');
        }

        $data['community_id'] = $community->id;
        $data['position'] = $community->curzzos()->count();

        Curzzo::create($data);

        return back()->with('success', 'Curzzo created!');
    }

    public function update(Request $request, Community $community, Curzzo $curzzo, FileStorage $storage): RedirectResponse
    {
        $this->authorize('update', $community);
        abort_unless($curzzo->community_id === $community->id, 404);

        $data = $request->validate([
            'name'         => ['sometimes', 'required', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:500'],
            'instructions' => ['sometimes', 'required', 'string', 'max:5000'],
            'personality'  => ['nullable', 'array'],
            'personality.tone'           => ['nullable', 'string', 'in:friendly,professional,casual,formal'],
            'personality.expertise'      => ['nullable', 'string', 'max:200'],
            'personality.response_style' => ['nullable', 'string', 'in:concise,detailed,conversational'],
            'avatar'                     => ['nullable', 'image', 'max:2048'],
            'model_tier'                 => ['sometimes', 'string', Rule::in(array_keys(config('curzzos.tiers')))],
            'remove_avatar'              => ['sometimes', 'boolean'],
            'is_active'                  => ['sometimes', 'boolean'],
            'price'                      => ['nullable', 'numeric', 'min:0'],
            'currency'                   => ['nullable', 'string', 'in:PHP,USD'],
            'billing_type'               => ['nullable', 'string', 'in:one_time,monthly'],
            'affiliate_commission_rate'  => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        if ($request->hasFile('avatar')) {
            $storage->delete($curzzo->avatar);
            $data['avatar'] = $storage->upload($request->file('avatar'), 'curzzo-avatars');
        } elseif (! empty($data['remove_avatar'])) {
            $storage->delete($curzzo->avatar);
            $data['avatar'] = null;
        }
        unset($data['remove_avatar']);

        $curzzo->update($data);

        return back()->with('success', 'Curzzo updated!');
    }

    public function destroy(Community $community, Curzzo $curzzo, FileStorage $storage): RedirectResponse
    {
        $this->authorize('update', $community);
        abort_unless($curzzo->community_id === $community->id, 404);

        $storage->delete($curzzo->avatar);
        $curzzo->delete();

        return back()->with('success', 'Curzzo deleted!');
    }
}
