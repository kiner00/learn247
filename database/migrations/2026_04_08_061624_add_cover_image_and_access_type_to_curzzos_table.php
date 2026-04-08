<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curzzos', function (Blueprint $table) {
            $table->string('cover_image')->nullable()->after('avatar');
            $table->string('preview_video')->nullable()->after('cover_image');
            $table->enum('access_type', ['free', 'inclusive', 'paid_once', 'paid_monthly', 'member_once'])
                ->default('free')
                ->after('preview_video');
        });
    }

    public function down(): void
    {
        Schema::table('curzzos', function (Blueprint $table) {
            $table->dropColumn(['cover_image', 'preview_video', 'access_type']);
        });
    }
};
