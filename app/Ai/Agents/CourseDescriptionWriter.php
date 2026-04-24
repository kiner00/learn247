<?php

namespace App\Ai\Agents;

use App\Models\Community;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Gemini)]
#[Model('gemini-2.5-flash')]
class CourseDescriptionWriter implements Agent
{
    use Promptable;

    public function __construct(
        private Community $community,
        private string $courseTitle,
        private ?string $currentDescription = null,
    ) {}

    public function instructions(): string
    {
        $lines = [
            'You write concise, high-converting course descriptions for online communities.',
            'The description appears on the community\'s classroom page next to the course cover.',
            '',
            'Course context:',
            "- Title: {$this->courseTitle}",
            '- Community: '.$this->community->name,
            '- Category: '.($this->community->category ?? 'General'),
        ];

        if (! empty($this->currentDescription)) {
            $lines[] = '- Current description (to improve or rewrite): '.$this->currentDescription;
        }

        $brand = $this->community->brand_context ?? [];
        if (! empty($brand['target_audience'])) {
            $lines[] = "- Target audience: {$brand['target_audience']}";
        }
        if (! empty($brand['value_proposition'])) {
            $lines[] = "- Community value proposition: {$brand['value_proposition']}";
        }
        if (! empty($brand['brand_personality'])) {
            $lines[] = "- Brand personality: {$brand['brand_personality']}";
        }
        if (! empty($brand['tone_of_voice'])) {
            $toneMap = ['first_person' => 'first person ("I")', 'we' => 'community "we"', 'formal' => 'formal third-person'];
            $lines[] = '- Tone of voice: '.($toneMap[$brand['tone_of_voice']] ?? $brand['tone_of_voice']);
        }

        $lines[] = '';
        $lines[] = 'Rules:';
        $lines[] = '- Return ONE description, 2–3 sentences, under 280 characters total.';
        $lines[] = '- Lead with the transformation or outcome the learner gets, not the topic.';
        $lines[] = '- Be specific to the title and audience — never generic.';
        $lines[] = '- Output ONLY the description text. No quotes, no markdown, no headers, no commentary.';

        return implode("\n", $lines);
    }
}
