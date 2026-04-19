<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 16); // 'agent' | 'image'
            $table->string('provider', 32)->nullable();
            $table->string('model', 64)->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->string('invocation_id', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
            $table->index('invocation_id');
            $table->index(['community_id', 'created_at']);
            $table->index(['model', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
