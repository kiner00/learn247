<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certification_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certification_id')->constrained('course_certifications')->cascadeOnDelete();
            $table->foreignId('affiliate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('xendit_id')->nullable()->index();
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'certification_id']);
        });

        Schema::table('affiliate_conversions', function (Blueprint $table) {
            $table->foreignId('certification_purchase_id')->nullable()->after('course_enrollment_id')
                ->constrained('certification_purchases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_conversions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('certification_purchase_id');
        });

        Schema::dropIfExists('certification_purchases');
    }
};
