<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->text('resend_api_key')->nullable()->after('sms_device_url');
            $table->string('resend_from_email')->nullable()->after('resend_api_key');
            $table->string('resend_from_name')->nullable()->after('resend_from_email');
            $table->string('resend_domain_id')->nullable()->after('resend_from_name');
            $table->string('resend_domain_status', 50)->nullable()->after('resend_domain_id');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn([
                'resend_api_key',
                'resend_from_email',
                'resend_from_name',
                'resend_domain_id',
                'resend_domain_status',
            ]);
        });
    }
};
