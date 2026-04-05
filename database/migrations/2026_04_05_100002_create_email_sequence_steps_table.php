<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_sequence_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained('email_sequences')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->unsignedInteger('delay_hours')->default(0); // hours after previous step (0 = immediate)
            $table->string('subject');
            $table->longText('html_body');
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->timestamps();

            $table->index(['sequence_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_sequence_steps');
    }
};
