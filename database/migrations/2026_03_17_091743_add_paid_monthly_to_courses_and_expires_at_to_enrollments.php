<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modify access_type column to support paid_monthly
        DB::statement("ALTER TABLE courses MODIFY access_type ENUM('free','inclusive','paid_once','paid_monthly') NOT NULL DEFAULT 'inclusive'");

        // Add expires_at to enrollments so monthly course subs can expire
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE courses MODIFY access_type ENUM('free','inclusive','paid_once') NOT NULL DEFAULT 'inclusive'");

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
