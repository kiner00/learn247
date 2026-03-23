<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            // *.curzzo.com subdomain (available to all plans)
            $table->string('subdomain', 63)->nullable()->unique()->after('slug');

            // Fully custom domain e.g. myclassroom.com (Pro plan only)
            $table->string('custom_domain', 253)->nullable()->unique()->after('subdomain');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn(['subdomain', 'custom_domain']);
        });
    }
};
