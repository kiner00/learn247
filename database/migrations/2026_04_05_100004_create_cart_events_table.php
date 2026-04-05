<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable(); // for guest users
            $table->string('event_type', 30); // checkout_started, payment_completed, abandoned
            $table->string('reference_type', 50)->nullable(); // subscription, course_enrollment
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('metadata')->nullable(); // extra context (amount, plan, course_id)
            $table->boolean('abandoned_email_sent')->default(false);
            $table->timestamps();

            $table->index(['community_id', 'event_type']);
            $table->index(['user_id', 'event_type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_events');
    }
};
