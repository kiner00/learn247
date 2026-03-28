<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE courses MODIFY COLUMN access_type ENUM('free','inclusive','paid_once','paid_monthly','member_once') NOT NULL DEFAULT 'inclusive'");
        } elseif (DB::getDriverName() === 'sqlite') {
            // SQLite enum columns use a CHECK constraint. Rebuild the table to update it.
            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn('access_type');
            });
            Schema::table('courses', function (Blueprint $table) {
                $table->enum('access_type', ['free', 'inclusive', 'paid_once', 'paid_monthly', 'member_once'])->default('inclusive')->after('position');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE courses MODIFY COLUMN access_type ENUM('free','inclusive','paid_once','paid_monthly') NOT NULL DEFAULT 'inclusive'");
        }
    }
};
