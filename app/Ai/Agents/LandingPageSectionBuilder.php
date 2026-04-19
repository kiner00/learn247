<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Gemini)]
#[Model('gemini-2.5-pro')]
class LandingPageSectionBuilder implements Agent
{
    use Promptable;

    public function __construct(private array $context) {}

    public function instructions(): string
    {
        $name = $this->context['name'];
        $category = $this->context['category'] ?? 'General';
        $description = $this->context['description'] ?? '';
        $price = $this->context['price'] ?? 0;
        $currency = $this->context['currency'] ?? 'PHP';
        $creatorName = $this->context['creator_name'] ?? 'the creator';
        $memberCount = $this->context['member_count'] ?? 0;
        $section = $this->context['section'];
        $priceLabel = $price > 0 ? "{$currency} {$price}" : 'Free';
        $schema = $this->getSectionSchema($section, $currency, $price);

        return implode("\n", [
            'You are an elite funnel copywriter specializing in high-converting landing pages for online communities, courses, and coaching programs.',
            'You write in the style of top marketers: clear, confident, benefit-driven, and emotionally compelling.',
            '',
            'Community details:',
            "  Name: {$name}",
            "  Category: {$category}",
            "  Description: {$description}",
            "  Creator: {$creatorName}",
            "  Price: {$priceLabel}",
            "  Members: {$memberCount}",
            '',
            "Generate ONLY the '{$section}' section as a JSON object (or array if specified).",
            'Return ONLY valid JSON — no markdown, no code fences, no extra keys.',
            'Be specific to the community name and category. Never be generic.',
            'Use active voice and strong verbs.',
            '',
            "Required JSON shape for '{$section}':",
            $schema,
        ]);
    }

    private function getSectionSchema(string $section, string $currency, mixed $price): string
    {
        $priceExample = $price > 0 ? "{$currency} ".number_format((float) $price) : "{$currency} 999";

        return match ($section) {
            'hero' => '{ "headline": "Bold, benefit-driven headline (max 80 chars)", "subheadline": "Supporting sentence expanding the promise (max 150 chars)", "cta_label": "CTA button text (max 30 chars)" }',

            'social_proof' => '{ "stat_label": "e.g. \'members and growing\'", "trust_line": "Short trust statement (max 80 chars)" }',

            'benefits' => '{ "headline": "Section headline (max 60 chars)", "items": [{ "icon": "emoji", "title": "Short title", "body": "1-2 sentence description" }, ...exactly 4 items] }',

            'for_you' => '{ "headline": "This is for you if... (max 60 chars)", "points": ["point 1", "point 2", "point 3"] (exactly 3, each max 80 chars) }',

            'creator' => '{ "headline": "Meet Your [Title] (max 50 chars)", "bio": "2-3 sentence creator bio in 3rd person (max 300 chars)" }',

            'testimonials' => '[{ "name": "First Last", "role": "short role/title", "quote": "compelling 1-2 sentence quote (max 180 chars)" }, ...exactly 3]',

            'faq' => '[{ "question": "Common question", "answer": "Clear answer (max 150 chars)" }, ...exactly 4]',

            'cta_section' => '{ "headline": "Closing urgency headline (max 80 chars)", "subtext": "Reassurance line (max 120 chars)", "cta_label": "Final CTA button text (max 30 chars)" }',

            'offer_stack' => '{ "headline": "Here\'s everything you get today (max 80 chars)", "items": [{ "name": "Component name", "value": "'.$priceExample.'", "description": "One sentence on what it includes" }, ...3-5 items], "total_value": "'.$priceExample.'", "price": "'.$priceExample.'", "price_note": "/month or \'\' if one-time" }',

            'guarantee' => '{ "headline": "My Iron-Clad, 100% Money-Back Guarantee (max 80 chars)", "days": 30, "body": "Clear, reassuring guarantee statement (max 250 chars)" }',

            'price_justification' => '{ "headline": "You have two choices (max 80 chars)", "options": [{ "label": "Option 1", "description": "Describe the costly/slow path (max 150 chars)" }, { "label": "Option 2", "description": "Describe expensive alternative (max 150 chars)" }, { "label": "The Smart Choice", "description": "Describe your offer as the obvious choice (max 150 chars)" }] }',

            default => '{}',
        };
    }
}
