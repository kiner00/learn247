<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curzzos', function (Blueprint $table) {
            $table->boolean('preview_video_sound')->default(false)->after('preview_video');
        });
    }

    public function down(): void
    {
        Schema::table('curzzos', function (Blueprint $table) {
            $table->dropColumn('preview_video_sound');
        });
    }
};
