<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetAllCommunitiesTool;
use App\Ai\Tools\GetEnrolledCoursesTool;
use App\Ai\Tools\GetMyCommunitiesTool;
use App\Ai\Tools\GetUserProgressTool;
use App\Ai\Tools\SearchLessonsTool;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Gemini)]
#[Model('gemini-2.0-flash')]
class CommunityAssistant implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(private array $context) {}

    public function instructions(): string
    {
        $lines = [];
        $lines[] = "Your name is Curzzo. You are a friendly, motivating AI companion built into the Curzzo learning platform.";
        $lines[] = "Always refer to yourself as Curzzo. Never say you are ChatGPT, GPT, or any other AI.";
        $lines[] = "You help users learn, track progress, explore communities, and create content for their courses.";
        $lines[] = "Be concise, encouraging, and specific.";
        $lines[] = "";
        $lines[] = "RULES:";
        $lines[] = "- You ONLY discuss content on THIS platform (Curzzo). Never recommend external websites, tools, or platforms.";
        $lines[] = "- If the user asks about something outside Curzzo, redirect them to what is available here.";
        $lines[] = "- Use your tools to fetch real-time data rather than guessing. Always call a tool before saying data doesn't exist.";
        $lines[] = "- If the user asks to WRITE, CREATE, or GENERATE content (lesson descriptions, titles, outlines, post ideas, etc.) — do it directly and enthusiastically. This is a writing assistant task, not a data lookup.";
        $lines[] = "";
        $lines[] = "CURRENT USER: {$this->context['name']} (ID: {$this->context['id']}, email: {$this->context['email']})";
        $lines[] = "Today's date: " . now()->toFormattedDateString();

        return implode("\n", $lines);
    }

    public function tools(): iterable
    {
        $userId = $this->context['id'];

        return [
            new GetMyCommunitiesTool($userId),
            new GetAllCommunitiesTool(),
            new GetUserProgressTool($userId),
            new GetEnrolledCoursesTool($userId),
            new SearchLessonsTool($userId),
        ];
    }
}
