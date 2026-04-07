<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_conversions', function (Blueprint $table) {
            $table->foreignId('curzzo_purchase_id')->nullable()->after('certification_purchase_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_conversions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('curzzo_purchase_id');
        });
    }
};
