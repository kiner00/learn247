<?php

namespace App\Actions\Community;

use App\Ai\Agents\LandingPageSectionBuilder;
use App\Models\Community;
use Illuminate\Support\Facades\Log;

class RegenerateLandingSection
{
    private const VALID_SECTIONS = [
        'hero', 'social_proof', 'benefits', 'for_you', 'creator',
        'testimonials', 'faq', 'cta_section', 'offer_stack',
        'guarantee', 'price_justification',
    ];

    /**
     * @return array{section: string, data: array}
     * @throws \RuntimeException on AI failure
     */
    public function execute(Community $community, string $section): array
    {
        if (! in_array($section, self::VALID_SECTIONS)) {
            throw new \InvalidArgumentException("Invalid section: {$section}");
        }

        $community->load('owner')->loadCount('members');

        $agent = new LandingPageSectionBuilder([
            'name'         => $community->name,
            'category'     => $community->category,
            'description'  => $community->description,
            'price'        => $community->price,
            'currency'     => $community->currency ?? 'PHP',
            'creator_name' => $community->owner->name ?? 'Creator',
            'member_count' => $community->members_count ?? 0,
            'section'      => $section,
        ]);

        $response = $agent->prompt("Regenerate the '{$section}' section now. Return ONLY valid JSON for this section, no markdown.");
        $raw = trim($response->text);
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);

        $data = json_decode($raw, true);

        if ($data === null) {
            throw new \RuntimeException('AI returned invalid JSON. Please try again.');
        }

        $current = $community->landing_page ?? [];
        $current[$section] = $data;
        $community->update(['landing_page' => $current]);

        return ['section' => $section, 'data' => $data];
    }
}
