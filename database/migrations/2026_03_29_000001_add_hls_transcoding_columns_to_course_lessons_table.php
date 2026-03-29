<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_lessons', function (Blueprint $table) {
            $table->string('video_hls_path')->nullable()->after('video_path');
            $table->string('video_transcode_status')->nullable()->after('video_hls_path');
            $table->unsignedTinyInteger('video_transcode_percent')->default(0)->after('video_transcode_status');
        });
    }

    public function down(): void
    {
        Schema::table('course_lessons', function (Blueprint $table) {
            $table->dropColumn(['video_hls_path', 'video_transcode_status', 'video_transcode_percent']);
        });
    }
};
