<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::OpenAI)]
#[Model('gpt-4o-mini')]
class LandingPageBuilder implements Agent
{
    use Promptable;

    public function __construct(private array $context) {}

    public function instructions(): string
    {
        $name        = $this->context['name'];
        $category    = $this->context['category'] ?? 'General';
        $description = $this->context['description'] ?? '';

        return implode("\n", [
            "You are a high-converting copywriter for online communities and courses.",
            "Your job is to generate compelling landing page copy for a community on the Curzzo platform.",
            "",
            "Community name: {$name}",
            "Category: {$category}",
            "Existing description: {$description}",
            "",
            "Return ONLY a valid JSON object (no markdown, no code fences) with these exact keys:",
            '  "tagline"     — a punchy one-liner (max 80 chars) that captures the community\'s promise',
            '  "description" — a 2–3 sentence description (max 300 chars) that sells the community to new members',
            '  "cta"         — a short call-to-action button label (max 30 chars)',
            "",
            "Rules:",
            "- Be specific and confident, not vague or generic.",
            "- Use active voice.",
            "- Do not invent facts. Base everything on the community name and category.",
            "- Output only valid JSON — no commentary.",
        ]);
    }
}
