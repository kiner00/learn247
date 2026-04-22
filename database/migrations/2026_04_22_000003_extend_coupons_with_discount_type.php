<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->enum('type', ['plan_grant', 'discount'])->default('plan_grant')->after('code');
            $table->enum('applies_to', ['monthly', 'annual', 'both'])->nullable()->after('plan');
            $table->decimal('discount_percent', 5, 2)->nullable()->after('applies_to');
            $table->unsignedSmallInteger('duration_months')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['type', 'applies_to', 'discount_percent']);
            $table->unsignedSmallInteger('duration_months')->nullable(false)->change();
        });
    }
};
