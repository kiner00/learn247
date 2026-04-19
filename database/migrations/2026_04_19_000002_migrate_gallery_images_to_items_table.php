<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('communities')
            ->whereNotNull('gallery_images')
            ->orderBy('id')
            ->chunkById(200, function ($communities) {
                $rows = [];
                $now = now();

                foreach ($communities as $community) {
                    $items = json_decode($community->gallery_images, true) ?: [];
                    foreach ($items as $position => $url) {
                        if (! is_string($url) || $url === '') {
                            continue;
                        }
                        $rows[] = [
                            'community_id' => $community->id,
                            'type' => 'image',
                            'image_path' => $url,
                            'transcode_percent' => 0,
                            'position' => $position,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if ($rows) {
                    DB::table('community_gallery_items')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        DB::table('community_gallery_items')->where('type', 'image')->delete();
    }
};
