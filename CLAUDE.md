# Project: Learn247 (Curzzo)

Community-based learning platform built with Laravel 12 + Vue 3 (Inertia.js).

## AI Feature Development Rules

### Brand Source of Truth (`brand_context`)

Every AI feature that generates content for a community **MUST** reference the community's `brand_context` JSON field as its source of truth. This applies to:

- AI Agents (`app/Ai/Agents/`) — system prompts must inject relevant brand_context fields
- AI image generation jobs (`app/Jobs/Generate*`) — prompts must include visual style, colors, personality
- Landing page generation — must use value proposition, tone, CTA goal, audience
- Any future AI feature that produces text, images, or structured content for a community

#### What `brand_context` contains

| Key | Used for |
|---|---|
| `brand_personality` | Tone matching (e.g. "Professional yet gritty") |
| `target_audience` | Audience-aware copy and imagery |
| `tone_of_voice` | `first_person`, `we`, or `formal` |
| `value_proposition` | Core transformation statement |
| `primary_keywords` | SEO, descriptions, alt-text |
| `big_problem` | Agitation copy, "why join" sections |
| `color_primary`, `color_secondary`, `color_accent` | Hex codes for visual consistency |
| `visual_style` | Image generation style guide |
| `logo_rules` | Logo placement constraints |
| `cta_goal` | Primary call-to-action label |
| `offer_details` | Pricing/offer for banners and CTAs |
| `social_share_description` | OG tags, meta descriptions |

#### How to use it

```php
// In an Agent's instructions() method:
$brand = $this->community->brand_context ?? [];
if (! empty($brand['target_audience']))   $lines[] = "- Target audience: {$brand['target_audience']}";
if (! empty($brand['tone_of_voice']))     $lines[] = "- Tone: {$brand['tone_of_voice']}";
// ... add all relevant fields

// In an image generation job:
$brand = $this->community->brand_context ?? [];
$styleHints = [];
if (! empty($brand['visual_style']))     $styleHints[] = "Visual style: {$brand['visual_style']}.";
if (! empty($brand['color_primary']))    $styleHints[] = "Primary color: {$brand['color_primary']}.";
// ... append to image prompt
```

#### Rules

1. **Always null-safe**: Use `$community->brand_context ?? []` and check `! empty()` — brand_context is nullable and fields are optional.
2. **Custom instructions have highest priority**: In chatbot agents, creator's `ai_chatbot_instructions` must appear LAST in the system prompt, marked as highest priority, overriding default behavior.
3. **Never hardcode brand values**: If a brand field exists for it, use it. Don't hardcode colors, CTA labels, or audience assumptions.
4. **Graceful degradation**: If brand_context is empty, AI features must still work with sensible defaults (community name + category).

### AI Agent System Prompt Structure

When building system prompts for community AI agents, follow this order:

1. Identity (who the AI is)
2. Community info (name, category, description, brand context)
3. Behavior rules (general guidelines)
4. Creator's custom instructions (LAST, HIGHEST PRIORITY)

This ordering ensures custom instructions override defaults.

## Tech Stack

- **Backend**: Laravel 12, PHP 8.2, MySQL, Redis
- **Frontend**: Vue 3 (Composition API + `<script setup>`), Inertia.js, Tailwind CSS
- **AI**: Laravel AI package (`laravel/ai`), Gemini for chat, image generation via `Laravel\Ai\Image`
- **Storage**: Laravel Storage (S3-compatible)
- **Queue**: Laravel queues for async jobs

## Conventions

- Settings pages live in `resources/js/Pages/Communities/Settings/`
- AI agents live in `app/Ai/Agents/`, tools in `app/Ai/Tools/`
- Community JSON columns (`landing_page`, `gallery_images`, `brand_context`) are cast to `array` in the model
- Forms use Inertia's `useForm()` with `transform(data => ({ ...data, _method: 'PATCH' })).post()` pattern for updates
