<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_level_perks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['community_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_level_perks');
    }
};
