<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_conversions', function (Blueprint $table) {
            // Make subscription_id optional (course purchases don't have a subscription)
            $table->foreignId('subscription_id')->nullable()->change();
            $table->foreignId('course_enrollment_id')->nullable()->after('subscription_id')->constrained('course_enrollments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_conversions', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\CourseEnrollment::class);
            $table->dropColumn('course_enrollment_id');
            $table->foreignId('subscription_id')->nullable(false)->change();
        });
    }
};
