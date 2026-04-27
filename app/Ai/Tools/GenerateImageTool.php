<?php

namespace App\Ai\Tools;

use App\Exceptions\AiBudgetExceededException;
use App\Models\Community;
use App\Services\Ai\BudgetGuard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Image;
use Laravel\Ai\Tools\Request;

class GenerateImageTool implements Tool
{
    public const MAX_PROMPT_LENGTH = 1000;

    private const ALLOWED_ASPECTS = ['1:1', '3:2', '2:3', '16:9', '9:16', '4:3', '3:4'];

    public function __construct(
        private Community $community,
        private ?int $userId = null,
    ) {}

    public function name(): string
    {
        return 'generate_image';
    }

    public function description(): string
    {
        return 'Generate a brand-aligned image from a text description. Use this whenever the member asks for an image, banner, cover, thumbnail, poster, infographic, logo concept, or visual asset — including course banners, course thumbnails, lesson cover art, and social graphics. Returns a markdown image link that you MUST include verbatim in your reply so the member sees the image.';
    }

    public function handle(Request $request): string
    {
        $prompt = trim($request->string('prompt', ''));
        if ($prompt === '') {
            return 'Error: prompt is required. Ask the member what the image should depict.';
        }

        $length = mb_strlen($prompt);
        if ($length > self::MAX_PROMPT_LENGTH) {
            return 'Error: prompt is too long ('.$length.' chars; max '.self::MAX_PROMPT_LENGTH.'). Ask the member for a shorter description focused on the key subject, mood, and composition.';
        }

        $aspect = (string) $request->string('aspect_ratio', '3:2');
        if (! in_array($aspect, self::ALLOWED_ASPECTS, true)) {
            $aspect = '3:2';
        }

        try {
            BudgetGuard::assertAllowed(userId: $this->userId, communityId: $this->community->id);
        } catch (AiBudgetExceededException $e) {
            Log::warning('Curzzo image generation blocked by budget guard', [
                'community_id' => $this->community->id,
                'user_id' => $this->userId,
                'reason' => $e->getMessage(),
            ]);

            return 'Error: the community has hit its AI spending cap for now. Tell the member to try again later.';
        }

        $fullPrompt = $this->buildPrompt($prompt);

        try {
            $response = Image::of($fullPrompt)->size($aspect)->generate();
            $image = $response->firstImage();
        } catch (\Throwable $e) {
            Log::error('Curzzo image generation failed', [
                'community_id' => $this->community->id,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return 'Error: image generation failed ('.$e->getMessage().'). Tell the member what went wrong in plain language and suggest they try again with a shorter or simpler description.';
        }

        $filename = 'curzzo-chat-images/'.$this->community->id.'/'.Str::uuid().'.png';
        Storage::put($filename, base64_decode($image->image));

        $url = Storage::url($filename);

        return "Image generated. Include this markdown VERBATIM in your reply so the member sees it:\n\n![generated image]({$url})";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'prompt' => $schema->string()->description('Detailed visual description. Include subject, mood, composition, and any on-image text. Keep it under '.self::MAX_PROMPT_LENGTH.' characters. Do NOT include brand colors or style (merged automatically), Stable-Diffusion-style "NEGATIVE PROMPT:" sections, or duplicated paragraphs — the image model rejects those.'),
            'aspect_ratio' => $schema->string()->description('Required. One of: 1:1, 3:2, 2:3, 16:9, 9:16, 4:3, 3:4. Choose by orientation: 9:16 for vertical / portrait / story / reel / phone wallpaper, 2:3 or 3:4 for other portrait formats, 16:9 for landscape course banners and YouTube thumbnails, 1:1 for square avatars/thumbnails/social posts, 3:2 as a fallback when no orientation is implied. If the user explicitly names a ratio (e.g. "9:16"), pass that exact ratio.'),
        ];
    }

    private function buildPrompt(string $userPrompt): string
    {
        $brand = $this->community->brand_context ?? [];

        $hints = [];
        if (! empty($brand['visual_style'])) {
            $hints[] = "Visual style: {$brand['visual_style']}.";
        }
        if (! empty($brand['brand_personality'])) {
            $hints[] = "Brand personality: {$brand['brand_personality']}.";
        }
        if (! empty($brand['color_primary'])) {
            $hints[] = "Primary color: {$brand['color_primary']}.";
        }
        if (! empty($brand['color_secondary'])) {
            $hints[] = "Secondary color: {$brand['color_secondary']}.";
        }
        if (! empty($brand['color_accent'])) {
            $hints[] = "Accent color: {$brand['color_accent']}.";
        }
        if (! empty($brand['target_audience'])) {
            $hints[] = "Target audience: {$brand['target_audience']}.";
        }

        if (empty($hints)) {
            return $userPrompt;
        }

        return $userPrompt.' '.implode(' ', $hints);
    }
}
