<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->string('sms_provider', 20)->nullable()->after('google_analytics_id'); // semaphore | vonage | xtreme_sms
            $table->string('sms_api_key')->nullable()->after('sms_provider');
            $table->string('sms_api_secret')->nullable()->after('sms_api_key');   // Vonage only
            $table->string('sms_sender_name', 11)->nullable()->after('sms_api_secret'); // Semaphore / Vonage sender ID
            $table->string('sms_device_url')->nullable()->after('sms_sender_name');     // Xtreme SMS gateway URL
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn(['sms_provider', 'sms_api_key', 'sms_api_secret', 'sms_sender_name', 'sms_device_url']);
        });
    }
};
