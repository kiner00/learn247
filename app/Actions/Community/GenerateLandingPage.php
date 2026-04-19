<?php

namespace App\Actions\Community;

use App\Ai\Agents\LandingPageBuilder;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class GenerateLandingPage
{
    /**
     * @return array The generated landing page data
     *
     * @throws \RuntimeException on AI failure or unexpected format
     */
    public function execute(Community $community, User $user): array
    {
        $agent = new LandingPageBuilder([
            'name' => $community->name,
            'category' => $community->category,
            'description' => $community->description,
            'price' => $community->price,
            'currency' => $community->currency ?? 'PHP',
            'creator_name' => $user->name,
            'member_count' => $community->members_count ?? $community->members()->count(),
        ]);

        $response = $agent->prompt(
            'Generate the full funnel landing page now. Return only valid JSON.'
        );

        $raw = trim($response->text);
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);

        $copy = json_decode($raw, true);

        if (! $copy || ! isset($copy['hero'], $copy['benefits'], $copy['faq'])) {
            Log::warning('LandingPageBuilder unexpected format', [
                'community' => $community->slug,
                'raw' => substr($raw, 0, 500),
            ]);

            throw new \RuntimeException('AI returned an unexpected format. Please try again.');
        }

        $copy['_sections'] = [
            ['type' => 'hero',                'visible' => true],
            ['type' => 'social_proof',        'visible' => isset($copy['social_proof'])],
            ['type' => 'benefits',            'visible' => isset($copy['benefits'])],
            ['type' => 'for_you',             'visible' => isset($copy['for_you'])],
            ['type' => 'creator',             'visible' => isset($copy['creator'])],
            ['type' => 'testimonials',        'visible' => ! empty($copy['testimonials'])],
            ['type' => 'offer_stack',         'visible' => false],
            ['type' => 'guarantee',           'visible' => false],
            ['type' => 'price_justification', 'visible' => false],
            ['type' => 'faq',                 'visible' => ! empty($copy['faq'])],
            ['type' => 'cta_section',         'visible' => true],
        ];

        $community->update(['landing_page' => $copy]);

        return $copy;
    }
}
