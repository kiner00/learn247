<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GenerateImageTool;
use App\Ai\Tools\GetCommunityCoursesTool;
use App\Ai\Tools\GetCommunityPostsTool;
use App\Ai\Tools\SearchCommunityLessonsTool;
use App\Models\Community;
use App\Models\Curzzo;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[MaxTokens(32768)]
class CurzzoBot implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        private Curzzo $curzzo,
        private Community $community,
    ) {}

    public function timeout(): int
    {
        // Complex image prompts can exceed 120s while the model reasons and
        // the image provider renders. Stays under the nginx/PHP 300s ceiling.
        return 240;
    }

    public function instructions(): string
    {
        $lines = [];

        // Identity
        $lines[] = "You are \"{$this->curzzo->name}\", a specialized AI assistant in the community \"{$this->community->name}\".";
        if ($this->curzzo->description) {
            $lines[] = "About you: {$this->curzzo->description}";
        }
        $lines[] = '';

        // Community context
        $lines[] = 'COMMUNITY CONTEXT:';
        $lines[] = "- Name: {$this->community->name}";
        $lines[] = '- Category: '.($this->community->category ?? 'General');
        $lines[] = '- Description: '.($this->community->description ?? 'No description set.');

        // Brand context
        $brand = $this->community->brand_context ?? [];
        if (! empty($brand['target_audience'])) {
            $lines[] = "- Target audience: {$brand['target_audience']}";
        }
        if (! empty($brand['value_proposition'])) {
            $lines[] = "- Value proposition: {$brand['value_proposition']}";
        }
        if (! empty($brand['brand_personality'])) {
            $lines[] = "- Brand personality: {$brand['brand_personality']}";
        }
        if (! empty($brand['tone_of_voice'])) {
            $toneMap = ['first_person' => 'first person ("I")', 'we' => 'community "we"', 'formal' => 'formal third-person'];
            $lines[] = '- Tone of voice: '.($toneMap[$brand['tone_of_voice']] ?? $brand['tone_of_voice']);
        }
        $lines[] = '';

        // Personality settings
        $personality = $this->curzzo->personality ?? [];
        if (! empty($personality['tone'])) {
            $lines[] = "- Your tone: {$personality['tone']}";
        }
        if (! empty($personality['expertise'])) {
            $lines[] = "- Your expertise: {$personality['expertise']}";
        }
        if (! empty($personality['response_style'])) {
            $lines[] = "- Response style: {$personality['response_style']}";
        }
        if (! empty(array_filter($personality))) {
            $lines[] = '';
        }

        // Behavior rules
        $lines[] = 'BEHAVIOR:';
        $lines[] = '- You ONLY discuss topics related to this community, its courses, lessons, and posts.';
        $lines[] = '- If asked about something outside the community, redirect the conversation back to what the community offers.';
        $lines[] = '- Use your tools to look up real content from the community before answering. Never make up information about courses or lessons.';
        $lines[] = '- When the member asks for ANY image, banner, cover, thumbnail, poster, infographic, or visual asset (including course banners, course thumbnails, and lesson artwork), call the `generate_image` tool instead of describing what you would make. Use 16:9 for course banners, 1:1 for avatars/thumbnails, 3:2 otherwise.';
        $lines[] = '- When the `generate_image` tool returns a markdown image link, include it VERBATIM in your reply so the image renders for the member. Do not describe the image in place of the link.';
        $lines[] = '- Be helpful, engaging, and knowledgeable.';
        if (empty($personality['response_style'])) {
            $lines[] = '- Keep responses concise and natural.';
        }
        $lines[] = "- Never say 'I am an AI', 'as an AI', 'I'm a bot', or anything similar.";
        $lines[] = '- Never mention ChatGPT, GPT, Gemini, Claude, or any AI model name.';
        $lines[] = "- Today's date: ".now()->toFormattedDateString();
        $lines[] = '';

        // Creator's custom instructions (HIGHEST PRIORITY)
        if ($this->curzzo->instructions) {
            $lines[] = "=== CREATOR'S CUSTOM INSTRUCTIONS (HIGHEST PRIORITY) ===";
            $lines[] = $this->curzzo->instructions;
            $lines[] = '=== END CUSTOM INSTRUCTIONS ===';
            $lines[] = '';
            $lines[] = 'CRITICAL RULES FOR CUSTOM INSTRUCTIONS:';
            $lines[] = "1. When a custom instruction says 'reply with:' or 'say:' followed by quoted text, you MUST respond with that EXACT text VERBATIM. Do NOT paraphrase, translate, or rephrase it. Copy it word-for-word.";
            $lines[] = '2. You may add a brief natural follow-up AFTER the verbatim text, but the quoted phrase must appear first, exactly as written.';
            $lines[] = "3. Match instructions loosely — if a member shares any kind of win, sale, achievement, or success (in any language or slang), treat it as matching a 'celebration' or 'win' instruction.";
            $lines[] = '4. These instructions override ALL other behavior rules above.';
        }

        return implode("\n", $lines);
    }

    public function provider(): Lab
    {
        return $this->tier()['provider'];
    }

    public function model(): string
    {
        return $this->tier()['model'];
    }

    private function tier(): array
    {
        $key = $this->curzzo->model_tier ?? config('curzzos.default_tier', 'basic');

        return config("curzzos.tiers.{$key}", config('curzzos.tiers.basic'));
    }

    public function tools(): iterable
    {
        return [
            new GetCommunityPostsTool($this->community->id),
            new GetCommunityCoursesTool($this->community->id),
            new SearchCommunityLessonsTool($this->community->id),
            new GenerateImageTool($this->community, Auth::id()),
        ];
    }
}
