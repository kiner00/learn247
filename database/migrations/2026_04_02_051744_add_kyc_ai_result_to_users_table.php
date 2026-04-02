<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('kyc_ai_result')->nullable()->after('kyc_rejected_reason');
            $table->unsignedTinyInteger('kyc_ai_rejections')->default(0)->after('kyc_ai_result');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['kyc_ai_result', 'kyc_ai_rejections']);
        });
    }
};
