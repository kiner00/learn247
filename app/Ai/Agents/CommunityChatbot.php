<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetCommunityCoursesTool;
use App\Ai\Tools\GetCommunityPostsTool;
use App\Ai\Tools\SearchCommunityLessonsTool;
use App\Models\Community;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Gemini)]
#[Model('gemini-2.5-flash')]
class CommunityChatbot implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(private Community $community) {}

    public function instructions(): string
    {
        $lines = [];
        $lines[] = "You are an AI assistant for the community \"{$this->community->name}\".";
        $lines[] = "You ONLY answer questions about this community, its courses, lessons, and posts.";
        $lines[] = "If asked about anything outside this community, politely redirect the user to ask about the community instead.";
        $lines[] = "";
        $lines[] = "COMMUNITY INFO:";
        $lines[] = "- Name: {$this->community->name}";
        $lines[] = "- Category: " . ($this->community->category ?? 'General');
        $lines[] = "- Description: " . ($this->community->description ?? 'No description set.');
        $lines[] = "- Owner: " . ($this->community->owner?->name ?? 'Unknown');
        $lines[] = "";

        if ($this->community->ai_chatbot_instructions) {
            $lines[] = "CREATOR INSTRUCTIONS (follow these rules set by the community creator):";
            $lines[] = $this->community->ai_chatbot_instructions;
            $lines[] = "";
        }

        $lines[] = "RULES:";
        $lines[] = "- Use your tools to look up real content before answering. Do not guess or make up information.";
        $lines[] = "- Be helpful, concise, and friendly.";
        $lines[] = "- If you don't know something, say so and suggest the user check the community's courses or posts.";
        $lines[] = "- Never mention that you are ChatGPT, GPT, or any specific AI model. You are the community's AI assistant.";
        $lines[] = "- Today's date: " . now()->toFormattedDateString();

        return implode("\n", $lines);
    }

    public function tools(): iterable
    {
        return [
            new GetCommunityPostsTool($this->community->id),
            new GetCommunityCoursesTool($this->community->id),
            new SearchCommunityLessonsTool($this->community->id),
        ];
    }
}
