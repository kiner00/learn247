<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('cert_title');
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->unsignedTinyInteger('pass_score')->default(70);
            $table->boolean('randomize_questions')->default(false);
            $table->timestamps();
        });

        Schema::create('certification_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certification_id')->constrained('course_certifications')->cascadeOnDelete();
            $table->text('question');
            $table->enum('type', ['multiple_choice', 'true_false'])->default('multiple_choice');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('certification_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('certification_questions')->cascadeOnDelete();
            $table->string('label');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });

        Schema::create('certification_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certification_id')->constrained('course_certifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('answers');
            $table->unsignedTinyInteger('score');
            $table->boolean('passed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['certification_id', 'user_id']);
        });

        // Add extra columns to certificates table
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('cert_title')->nullable()->after('course_id');
            $table->text('description')->nullable()->after('cert_title');
            $table->string('cover_image')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['cert_title', 'description', 'cover_image']);
        });

        Schema::dropIfExists('certification_attempts');
        Schema::dropIfExists('certification_question_options');
        Schema::dropIfExists('certification_questions');
        Schema::dropIfExists('course_certifications');
    }
};
