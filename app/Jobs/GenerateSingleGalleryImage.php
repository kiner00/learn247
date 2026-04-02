<?php

namespace App\Jobs;

use App\Models\Community;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;

class GenerateSingleGalleryImage implements ShouldQueue
{
    use Queueable;

    public int $tries   = 1;
    public int $timeout = 120;

    public function __construct(
        public Community $community,
        public int $promptIndex,
    ) {}

    public function handle(): void
    {
        $cacheKey = "gallery-generating:{$this->community->id}";
        Cache::put($cacheKey, ['status' => 'generating', 'progress' => 0, 'total' => 1], 300);

        $communityName = $this->community->name;
        $category      = $this->community->category ?? 'online learning';
        $brand         = $this->community->brand_context ?? [];

        // Build brand style context from Brand Source of Truth
        $styleHints = [];
        if (! empty($brand['visual_style']))       $styleHints[] = "Visual style: {$brand['visual_style']}.";
        if (! empty($brand['brand_personality']))   $styleHints[] = "Brand personality: {$brand['brand_personality']}.";
        if (! empty($brand['color_primary']))       $styleHints[] = "Primary color: {$brand['color_primary']}.";
        if (! empty($brand['color_secondary']))     $styleHints[] = "Secondary color: {$brand['color_secondary']}.";
        if (! empty($brand['color_accent']))        $styleHints[] = "Accent color: {$brand['color_accent']}.";
        if (! empty($brand['target_audience']))     $styleHints[] = "Target audience: {$brand['target_audience']}.";
        if (! empty($brand['value_proposition']))   $styleHints[] = "Value proposition: {$brand['value_proposition']}.";
        $brandSuffix = $styleHints ? ' ' . implode(' ', $styleHints) : '';

        $ctaLabel = ! empty($brand['cta_goal']) ? $brand['cta_goal'] : 'Join Now';
        $bigProblem = $brand['big_problem'] ?? '';
        $offer = $brand['offer_details'] ?? '';

        $prompts = [
            "A clean, professional welcome banner graphic that says 'Welcome to {$communityName}'. Modern design with gradient background, bold typography, and subtle decorative elements. Digital marketing style, 1200x800 resolution.{$brandSuffix}",
            "An infographic slide titled 'Why Join {$communityName}?' listing the top 3 benefits of joining this {$category} community." . ($bigProblem ? " Addresses the problem: {$bigProblem}." : '') . " Clean layout with icons, modern flat design, professional color scheme. 1200x800 resolution.{$brandSuffix}",
            "A realistic screenshot mockup of an online course platform dashboard showing a list of courses with progress bars, thumbnails, and lesson counts. Clean UI design with sidebar navigation. Represents the classroom of {$communityName}. 1200x800 resolution.{$brandSuffix}",
            "A realistic screenshot mockup of a calendar view showing upcoming live sessions, webinars, and events for an online community called {$communityName}. Modern UI with colorful event cards and time slots. 1200x800 resolution.{$brandSuffix}",
            "A professional certificate mockup with ornate border design, showing a 'Certificate of Completion' from {$communityName}. Elegant typography, gold accents, placeholder name field, and official seal. 1200x800 resolution.{$brandSuffix}",
            "A screenshot mockup of a community leaderboard showing top active members with avatars, points, badges, and rankings. Social proof display for {$communityName}. Gamification UI design, modern and engaging. 1200x800 resolution.{$brandSuffix}",
            "A mobile phone mockup showing the {$communityName} community app on a smartphone screen. Clean responsive design with course cards, navigation bar, and profile section. The phone is angled slightly with a clean background. 1200x800 resolution.{$brandSuffix}",
            "A compelling '{$ctaLabel}' call-to-action graphic for {$communityName}." . ($offer ? " Featuring: {$offer}." : '') . " Bold typography, vibrant gradient background, a prominent button, and an arrow pointing toward the button. Urgency and excitement in the design. 1200x800 resolution.{$brandSuffix}",
        ];

        $prompt = $prompts[$this->promptIndex] ?? $prompts[0];

        try {
            $imageResponse = Image::of($prompt)->size('3:2')->generate();
            $img           = $imageResponse->firstImage();

            $filename = 'community-gallery/' . $this->community->id . '_' . ($this->promptIndex + 1) . '_' . time() . '.png';
            Storage::put($filename, base64_decode($img->image));
            $url = Storage::url($filename);

            $gallery   = $this->community->fresh()->gallery_images ?? [];
            $gallery[] = $url;
            $this->community->update(['gallery_images' => $gallery]);

            Cache::put($cacheKey, ['status' => 'completed', 'progress' => 1, 'total' => 1], 300);
        } catch (\Throwable $e) {
            Log::error("Single gallery image generation failed", [
                'community'   => $this->community->id,
                'promptIndex' => $this->promptIndex,
                'error'       => $e->getMessage(),
            ]);

            Cache::put($cacheKey, [
                'status' => 'failed',
                'error'  => 'Image generation failed. Please try again.',
            ], 300);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Cache::put("gallery-generating:{$this->community->id}", [
            'status' => 'failed',
            'error'  => 'Image generation failed. Please try again.',
        ], 300);

        Log::error('GenerateSingleGalleryImage job failed', [
            'community' => $this->community->id,
            'error'     => $exception->getMessage(),
        ]);
    }
}
