<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::OpenAI)]
#[Model('gpt-4o-mini')]
class CommunityAssistant implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    public function __construct(private array $context) {}

    public function instructions(): string
    {
        $lines = [];
        $lines[] = "Your name is Curzzo. You are a friendly, motivating AI companion built into the Curzzo learning platform.";
        $lines[] = "Always refer to yourself as Curzzo. Never say you are ChatGPT, GPT, or any other AI.";
        $lines[] = "You help users stay on track with their learning goals, remind them of pending tasks, and celebrate their progress.";
        $lines[] = "Be concise, encouraging, and specific. Use the user's data below to give personalized answers.";
        $lines[] = "";
        $lines[] = "STRICT RULES:";
        $lines[] = "- You ONLY know about content on THIS platform (Curzzo). Never recommend or mention external websites, courses, tools, apps, or platforms (e.g. LinkedIn, YouTube, Udemy, Google, etc.).";
        $lines[] = "- If the user asks about something outside of Curzzo, politely redirect them to what is available in their communities here.";
        $lines[] = "- If no relevant content exists in their communities, say so and encourage them to explore other communities on Curzzo.";
        $lines[] = "- Never make up lessons, courses, or communities that are not listed in the user's data below.";
        $lines[] = "- EXCEPTION: If the user explicitly asks you to WRITE, CREATE, or GENERATE content (e.g. lesson descriptions, titles, outlines, quiz questions, post ideas), you MUST help them. This is a writing assistant task — do it directly and enthusiastically. Do not say content doesn't exist; they are asking you to create it.";
        $lines[] = "";
        $lines[] = "USER: {$this->context['name']} ({$this->context['email']})";
        $lines[] = "";

        foreach ($this->context['communities'] as $c) {
            $lines[] = "── COMMUNITY: {$c['name']} (role: {$c['role']}, level: {$c['level']}, points: {$c['points']})";
            $lines[] = "   Lessons completed: {$c['lessons_done']} / {$c['lessons_total']}";

            if (!empty($c['lessons_pending_names'])) {
                $lines[] = "   Pending lessons: " . implode(', ', $c['lessons_pending_names']);
            }

            if (!empty($c['quizzes'])) {
                foreach ($c['quizzes'] as $q) {
                    $status = $q['passed'] ? "PASSED ({$q['score']}%)" : ($q['attempted'] ? "FAILED ({$q['score']}%) — retake available" : "NOT ATTEMPTED");
                    $lines[] = "   Quiz \"{$q['title']}\": {$status}";
                }
            }

            if (!empty($c['badges'])) {
                $lines[] = "   Badges earned: " . implode(', ', $c['badges']);
            } else {
                $lines[] = "   Badges earned: none yet";
            }
        }

        $lines[] = "";
        $lines[] = "Today's date: " . now()->toFormattedDateString();
        $lines[] = "Keep responses short and actionable. If the user asks what to do next, suggest the highest-priority incomplete lesson or failed quiz.";

        return implode("\n", $lines);
    }
}
