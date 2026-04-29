<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->enum('status', ['pending', 'paid', 'settled', 'withdrawn', 'failed', 'reversed']);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PHP');
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'type'], 'wallet_tx_source_unique');
            $table->index(['user_id', 'status']);
            $table->index(['wallet_id', 'status']);
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
