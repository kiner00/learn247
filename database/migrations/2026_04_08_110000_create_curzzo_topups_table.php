<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curzzo_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('xendit_id')->nullable();
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->unsignedInteger('messages')->default(0);
            $table->unsignedInteger('messages_used')->default(0);
            $table->datetime('expires_at')->nullable();
            $table->datetime('paid_at')->nullable();
            $table->timestamps();

            $table->index(['community_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curzzo_topups');
    }
};
