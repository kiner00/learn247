<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crz_token_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 8);
            $table->string('type'); // 'award', 'spend', 'transfer'
            $table->string('reason')->nullable(); // e.g. 'early_bird_badge', 'early_builder_badge'
            $table->string('reference')->nullable(); // e.g. badge key or other reference
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crz_token_transactions');
    }
};
