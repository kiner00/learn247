<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_certifications', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->default(0)->after('randomize_questions');
            $table->unsignedTinyInteger('affiliate_commission_rate')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('course_certifications', function (Blueprint $table) {
            $table->dropColumn(['price', 'affiliate_commission_rate']);
        });
    }
};
