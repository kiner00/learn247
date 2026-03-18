<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->string('tiktok_pixel_id', 30)->nullable()->after('facebook_pixel_id');
            $table->string('google_analytics_id', 20)->nullable()->after('tiktok_pixel_id');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn(['tiktok_pixel_id', 'google_analytics_id']);
        });
    }
};
