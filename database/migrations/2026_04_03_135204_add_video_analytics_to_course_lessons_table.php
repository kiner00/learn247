<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('course_lessons', function (Blueprint $table) {
            $table->unsignedInteger('video_play_count')->default(0)->after('video_transcode_percent');
            $table->unsignedInteger('video_watch_seconds')->default(0)->after('video_play_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_lessons', function (Blueprint $table) {
            $table->dropColumn(['video_play_count', 'video_watch_seconds']);
        });
    }
};
