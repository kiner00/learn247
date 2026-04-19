<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['image', 'video']);
            $table->string('image_path')->nullable();
            $table->string('video_path')->nullable();
            $table->string('video_hls_path')->nullable();
            $table->string('poster_path')->nullable();
            $table->enum('transcode_status', ['pending', 'processing', 'completed', 'failed'])->nullable();
            $table->unsignedTinyInteger('transcode_percent')->default(0);
            $table->string('mediaconvert_job_id')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['community_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_gallery_items');
    }
};
