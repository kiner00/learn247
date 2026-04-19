<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\EmailCampaign;
use App\Models\EmailSequence;
use App\Models\EmailSequenceStep;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmailSequenceController extends Controller
{
    public function index(Community $community): Response
    {
        $this->authorize('update', $community);

        $sequences = EmailSequence::where('community_id', $community->id)
            ->withCount(['steps', 'enrollments'])
            ->with('campaign:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($seq) => [
                'id' => $seq->id,
                'campaign_name' => $seq->campaign->name ?? '—',
                'campaign_id' => $seq->campaign_id,
                'trigger_event' => $seq->trigger_event,
                'trigger_filter' => $seq->trigger_filter,
                'status' => $seq->status,
                'steps_count' => $seq->steps_count,
                'enrollments_count' => $seq->enrollments_count,
                'created_at' => $seq->created_at,
            ]);

        return Inertia::render('Communities/Email/Sequences', [
            'community' => $community,
            'sequences' => $sequences,
            'hasResendKey' => (bool) $community->resend_api_key,
        ]);
    }

    public function create(Community $community): Response
    {
        $this->authorize('update', $community);

        $tags = Tag::where('community_id', $community->id)->orderBy('name')->get(['id', 'name']);
        $courses = $community->courses()->select('id', 'title')->get();

        return Inertia::render('Communities/Email/SequenceCreate', [
            'community' => $community,
            'tags' => $tags,
            'courses' => $courses,
            'triggers' => EmailSequence::TRIGGERS,
        ]);
    }

    public function store(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        if (! $community->resend_api_key) {
            return back()->withErrors(['resend' => 'Please configure your Resend API key first.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger_event' => ['required', 'string', Rule::in(EmailSequence::TRIGGERS)],
            'trigger_filter' => ['nullable', 'array'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.subject' => ['required', 'string', 'max:255'],
            'steps.*.html_body' => ['required', 'string', 'max:100000'],
            'steps.*.delay_hours' => ['required', 'integer', 'min:0', 'max:8760'],
        ]);

        $campaign = EmailCampaign::create([
            'community_id' => $community->id,
            'name' => $data['name'],
            'type' => EmailCampaign::TYPE_SEQUENCE,
            'status' => EmailCampaign::STATUS_DRAFT,
        ]);

        $sequence = EmailSequence::create([
            'campaign_id' => $campaign->id,
            'community_id' => $community->id,
            'trigger_event' => $data['trigger_event'],
            'trigger_filter' => $data['trigger_filter'] ?? null,
            'status' => EmailSequence::STATUS_DRAFT,
        ]);

        foreach ($data['steps'] as $i => $stepData) {
            EmailSequenceStep::create([
                'sequence_id' => $sequence->id,
                'position' => $i + 1,
                'delay_hours' => $stepData['delay_hours'],
                'subject' => $stepData['subject'],
                'html_body' => $stepData['html_body'],
                'from_email' => $community->resend_from_email,
                'from_name' => $community->resend_from_name ?? $community->name,
            ]);
        }

        return redirect()->route('communities.email-sequences.show', [$community, $sequence])
            ->with('success', 'Sequence created.');
    }

    public function show(Community $community, EmailSequence $sequence): Response
    {
        $this->authorize('update', $community);
        abort_unless($sequence->community_id === $community->id, 404);

        $sequence->load(['steps', 'campaign:id,name']);

        $enrollmentStats = [
            'active' => $sequence->enrollments()->where('status', 'active')->count(),
            'completed' => $sequence->enrollments()->where('status', 'completed')->count(),
            'cancelled' => $sequence->enrollments()->where('status', 'cancelled')->count(),
        ];

        return Inertia::render('Communities/Email/SequenceShow', [
            'community' => $community,
            'sequence' => $sequence,
            'enrollmentStats' => $enrollmentStats,
        ]);
    }

    public function activate(Request $request, Community $community, EmailSequence $sequence): RedirectResponse
    {
        $this->authorize('update', $community);
        abort_unless($sequence->community_id === $community->id, 404);

        if ($sequence->steps()->count() === 0) {
            return back()->withErrors(['sequence' => 'Add at least one step before activating.']);
        }

        $sequence->update(['status' => EmailSequence::STATUS_ACTIVE]);
        $sequence->campaign->update(['status' => 'sending']);

        return back()->with('success', 'Sequence activated! Members matching the trigger will be enrolled automatically.');
    }

    public function pause(Request $request, Community $community, EmailSequence $sequence): RedirectResponse
    {
        $this->authorize('update', $community);
        abort_unless($sequence->community_id === $community->id, 404);

        $sequence->update(['status' => EmailSequence::STATUS_PAUSED]);
        $sequence->campaign->update(['status' => 'paused']);

        return back()->with('success', 'Sequence paused.');
    }

    public function destroy(Community $community, EmailSequence $sequence): RedirectResponse
    {
        $this->authorize('update', $community);
        abort_unless($sequence->community_id === $community->id, 404);

        $campaign = $sequence->campaign;
        $sequence->delete();
        $campaign->delete();

        return redirect()->route('communities.email-sequences.index', $community)
            ->with('success', 'Sequence deleted.');
    }
}
