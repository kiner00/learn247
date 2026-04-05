<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_sequence_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained('email_sequences')->cascadeOnDelete();
            $table->foreignId('community_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('current_step_id')->nullable()->constrained('email_sequence_steps')->nullOnDelete();
            $table->unsignedSmallInteger('steps_completed')->default(0);
            $table->string('status', 20)->default('active'); // active, completed, cancelled
            $table->timestamp('next_send_at')->nullable();
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['sequence_id', 'community_member_id'], 'seq_member_unique');
            $table->index(['status', 'next_send_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_sequence_enrollments');
    }
};
