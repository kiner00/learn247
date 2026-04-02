<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('kyc_status')->default('none')->after('kyc_verified_at');
            $table->string('kyc_id_document', 2048)->nullable()->after('kyc_status');
            $table->string('kyc_selfie', 2048)->nullable()->after('kyc_id_document');
            $table->timestamp('kyc_submitted_at')->nullable()->after('kyc_selfie');
            $table->text('kyc_rejected_reason')->nullable()->after('kyc_submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['kyc_status', 'kyc_id_document', 'kyc_selfie', 'kyc_submitted_at', 'kyc_rejected_reason']);
        });
    }
};
