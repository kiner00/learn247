<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagController extends Controller
{
    public function index(Community $community)
    {
        $this->authorize('update', $community);

        $tags = Tag::where('community_id', $community->id)
            ->withCount('members')
            ->orderBy('name')
            ->get();

        return response()->json($tags);
    }

    public function store(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'color'     => ['nullable', 'string', 'max:7'],
            'type'      => ['nullable', 'string', 'in:manual,automatic'],
            'auto_rule' => ['nullable', 'array'],
        ]);

        $slug = Str::slug($data['name']);

        if (Tag::where('community_id', $community->id)->where('slug', $slug)->exists()) {
            return back()->withErrors(['name' => 'A tag with this name already exists.']);
        }

        Tag::create([
            'community_id' => $community->id,
            'name'         => $data['name'],
            'slug'         => $slug,
            'color'        => $data['color'] ?? null,
            'type'         => $data['type'] ?? 'manual',
            'auto_rule'    => $data['auto_rule'] ?? null,
        ]);

        return back()->with('success', 'Tag created.');
    }

    public function update(Request $request, Community $community, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $community);
        abort_unless($tag->community_id === $community->id, 404);

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'color'     => ['nullable', 'string', 'max:7'],
            'type'      => ['nullable', 'string', 'in:manual,automatic'],
            'auto_rule' => ['nullable', 'array'],
        ]);

        $slug = Str::slug($data['name']);

        if (Tag::where('community_id', $community->id)->where('slug', $slug)->where('id', '!=', $tag->id)->exists()) {
            return back()->withErrors(['name' => 'A tag with this name already exists.']);
        }

        $tag->update([
            'name'      => $data['name'],
            'slug'      => $slug,
            'color'     => $data['color'] ?? $tag->color,
            'type'      => $data['type'] ?? $tag->type,
            'auto_rule' => $data['auto_rule'] ?? $tag->auto_rule,
        ]);

        return back()->with('success', 'Tag updated.');
    }

    public function destroy(Community $community, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $community);
        abort_unless($tag->community_id === $community->id, 404);

        $tag->delete();

        return back()->with('success', 'Tag deleted.');
    }

    public function assign(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        $data = $request->validate([
            'member_ids' => ['required', 'array'],
            'member_ids.*' => ['integer'],
            'tag_ids'    => ['required', 'array'],
            'tag_ids.*'  => ['integer'],
            'action'     => ['required', 'string', 'in:attach,detach'],
        ]);

        // Ensure tags belong to this community
        $validTagIds = Tag::where('community_id', $community->id)
            ->whereIn('id', $data['tag_ids'])
            ->pluck('id');

        // Ensure members belong to this community
        $members = CommunityMember::where('community_id', $community->id)
            ->whereIn('id', $data['member_ids'])
            ->get();

        foreach ($members as $member) {
            if ($data['action'] === 'attach') {
                $syncData = $validTagIds->mapWithKeys(fn ($id) => [$id => ['tagged_at' => now()]])->toArray();
                $member->tags()->syncWithoutDetaching($syncData);
            } else {
                $member->tags()->detach($validTagIds);
            }
        }

        $label = $data['action'] === 'attach' ? 'assigned to' : 'removed from';

        return back()->with('success', "Tags {$label} {$members->count()} member(s).");
    }
}
