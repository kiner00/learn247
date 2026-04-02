<?php

namespace App\Jobs;

use App\Models\Community;
use App\Services\StorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;

class GenerateGalleryImages implements ShouldQueue
{
    use Queueable;

    public int $tries   = 1;
    public int $timeout = 600; // 10 minutes for 8 images

    public function __construct(public Community $community) {}

    public function handle(): void
    {
        $cacheKey = "gallery-generating:{$this->community->id}";
        Cache::put($cacheKey, ['status' => 'generating', 'progress' => 0, 'total' => 8], 900);

        $communityName = $this->community->name;
        $category      = $this->community->category ?? 'online learning';

        $prompts = [
            "A clean, professional welcome banner graphic that says 'Welcome to {$communityName}'. Modern design with gradient background, bold typography, and subtle decorative elements. Digital marketing style, 1200x800 resolution.",
            "An infographic slide titled 'Why Join {$communityName}?' listing the top 3 benefits of joining this {$category} community. Clean layout with icons, modern flat design, professional color scheme. 1200x800 resolution.",
            "A realistic screenshot mockup of an online course platform dashboard showing a list of courses with progress bars, thumbnails, and lesson counts. Clean UI design with sidebar navigation. Represents the classroom of {$communityName}. 1200x800 resolution.",
            "A realistic screenshot mockup of a calendar view showing upcoming live sessions, webinars, and events for an online community called {$communityName}. Modern UI with colorful event cards and time slots. 1200x800 resolution.",
            "A professional certificate mockup with ornate border design, showing a 'Certificate of Completion' from {$communityName}. Elegant typography, gold accents, placeholder name field, and official seal. 1200x800 resolution.",
            "A screenshot mockup of a community leaderboard showing top active members with avatars, points, badges, and rankings. Social proof display for {$communityName}. Gamification UI design, modern and engaging. 1200x800 resolution.",
            "A mobile phone mockup showing the {$communityName} community app on a smartphone screen. Clean responsive design with course cards, navigation bar, and profile section. The phone is angled slightly with a clean background. 1200x800 resolution.",
            "A compelling 'Join Now' call-to-action graphic for {$communityName}. Bold typography, vibrant gradient background, a prominent sign-up button, and an arrow pointing toward the button. Urgency and excitement in the design. 1200x800 resolution.",
        ];

        $gallery = [];

        foreach ($prompts as $i => $prompt) {
            try {
                $imageResponse = Image::of($prompt)->size('3:2')->generate();
                $img           = $imageResponse->firstImage();

                // Store the generated image
                $filename = 'community-gallery/' . $this->community->id . '_' . ($i + 1) . '_' . time() . '.png';
                Storage::disk(config('filesystems.default'))->put($filename, base64_decode($img->image));
                $url = Storage::url($filename);

                $gallery[] = $url;

                Cache::put($cacheKey, ['status' => 'generating', 'progress' => $i + 1, 'total' => 8], 900);
            } catch (\Throwable $e) {
                Log::error("Gallery image generation failed for image " . ($i + 1), [
                    'community' => $this->community->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        if (! empty($gallery)) {
            // Replace existing gallery with generated images
            $this->community->update(['gallery_images' => $gallery]);
        }

        Cache::put($cacheKey, ['status' => 'completed', 'progress' => count($gallery), 'total' => 8], 300);
    }

    public function failed(\Throwable $exception): void
    {
        $cacheKey = "gallery-generating:{$this->community->id}";
        Cache::put($cacheKey, ['status' => 'failed', 'error' => 'Image generation failed. Please try again.'], 300);

        Log::error('GenerateGalleryImages job failed', [
            'community' => $this->community->id,
            'error'     => $exception->getMessage(),
        ]);
    }
}
