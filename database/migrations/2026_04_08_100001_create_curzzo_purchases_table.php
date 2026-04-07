<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curzzo_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curzzo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('xendit_id')->nullable();
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->datetime('paid_at')->nullable();
            $table->datetime('expires_at')->nullable();
            $table->timestamps();

            $table->index(['curzzo_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curzzo_purchases');
    }
};
