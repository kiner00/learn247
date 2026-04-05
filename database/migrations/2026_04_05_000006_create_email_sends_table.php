<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->nullable()->constrained('email_broadcasts')->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_member_id')->constrained()->cascadeOnDelete();
            $table->string('resend_email_id')->nullable();
            $table->string('status', 20)->default('queued'); // queued, sent, delivered, bounced, complained, failed
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestamps();

            $table->index(['broadcast_id', 'status']);
            $table->index('community_member_id');
            $table->index('resend_email_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_sends');
    }
};
