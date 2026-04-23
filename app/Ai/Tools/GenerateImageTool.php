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

        $aspect = $request->string('aspect_ratio', '3:2');
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

            return 'Error: image generation failed. Tell the member to try again or rephrase the request.';
        }

        $filename = 'curzzo-chat-images/'.$this->community->id.'/'.Str::uuid().'.png';
        Storage::put($filename, base64_decode($image->image));

        $url = Storage::url($filename);

        return "Image generated. Include this markdown VERBATIM in your reply so the member sees it:\n\n![generated image]({$url})";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'prompt' => $schema->string()->description('Detailed visual description. Include subject, mood, composition, and any on-image text. Do NOT include brand colors or style — those are merged automatically.'),
            'aspect_ratio' => $schema->string()->description('One of: 1:1, 3:2, 2:3, 16:9, 9:16, 4:3, 3:4. Use 16:9 for course banners, 1:1 for avatars, 3:2 for general banners.'),
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
