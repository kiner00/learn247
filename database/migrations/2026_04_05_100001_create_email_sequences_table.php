<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('email_campaigns')->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('trigger_event', 50); // member.joined, subscription.paid, course.enrolled, cart.abandoned, tag.added
            $table->json('trigger_filter')->nullable(); // {"membership_type":"free","course_id":5,"tag_id":3}
            $table->string('status', 20)->default('draft'); // draft, active, paused
            $table->timestamps();

            $table->index(['community_id', 'status']);
            $table->index('trigger_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_sequences');
    }
};
