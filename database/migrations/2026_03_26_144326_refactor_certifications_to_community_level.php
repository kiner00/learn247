<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. course_certifications: add community_id (if not already added), drop course_id
        if (! Schema::hasColumn('course_certifications', 'community_id')) {
            Schema::table('course_certifications', function (Blueprint $table) {
                $table->foreignId('community_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('course_certifications', 'course_id')) {
            Schema::table('course_certifications', function (Blueprint $table) {
                $table->dropForeign(['course_id']);
            });
            Schema::table('course_certifications', function (Blueprint $table) {
                $table->dropUnique(['course_id']);
                $table->dropColumn('course_id');
            });
        }

        // 2. certificates: add certification_id, drop course_id
        if (! Schema::hasColumn('certificates', 'certification_id')) {
            Schema::table('certificates', function (Blueprint $table) {
                $table->foreignId('certification_id')->nullable()->after('user_id')->constrained('course_certifications')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('certificates', 'course_id')) {
            Schema::table('certificates', function (Blueprint $table) {
                $table->dropForeign(['course_id']);
            });
            Schema::table('certificates', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'course_id']);
                $table->dropColumn('course_id');
            });
        }

        // Add unique constraint if not already present
        try {
            Schema::table('certificates', function (Blueprint $table) {
                $table->unique(['user_id', 'certification_id']);
            });
        } catch (\Throwable $e) {
            // Index may already exist
        }
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['certification_id']);
            $table->dropUnique(['user_id', 'certification_id']);
            $table->dropColumn('certification_id');
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'course_id']);
        });

        Schema::table('course_certifications', function (Blueprint $table) {
            $table->foreignId('course_id')->unique()->nullable()->constrained()->cascadeOnDelete();
            $table->dropForeign(['community_id']);
            $table->dropColumn('community_id');
        });
    }
};
