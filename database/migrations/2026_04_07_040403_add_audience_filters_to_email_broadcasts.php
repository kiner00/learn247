<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_broadcasts', function (Blueprint $table) {
            $table->json('filter_exclude_tags')->nullable()->after('filter_tags');
            $table->unsignedInteger('filter_registered_days')->nullable()->after('filter_exclude_tags');
        });
    }

    public function down(): void
    {
        Schema::table('email_broadcasts', function (Blueprint $table) {
            $table->dropColumn(['filter_exclude_tags', 'filter_registered_days']);
        });
    }
};
