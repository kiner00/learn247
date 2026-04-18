<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('trigger_event', 50);
            $table->json('trigger_filter')->nullable();
            $table->string('action_type', 50);
            $table->json('action_config');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('run_count')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['community_id', 'trigger_event', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
