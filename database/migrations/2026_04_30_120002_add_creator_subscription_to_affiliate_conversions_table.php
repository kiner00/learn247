<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_conversions', function (Blueprint $table) {
            $table->foreignId('creator_subscription_id')->nullable()->after('curzzo_purchase_id')->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('billing_month_index')->nullable()->after('is_lifetime');
            $table->timestamp('cohort_start_at')->nullable()->after('billing_month_index');

            $table->index(['affiliate_id', 'referred_user_id', 'cohort_start_at'], 'aff_conv_cohort_idx');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_conversions', function (Blueprint $table) {
            $table->dropIndex('aff_conv_cohort_idx');
            $table->dropConstrainedForeignId('creator_subscription_id');
            $table->dropColumn(['billing_month_index', 'cohort_start_at']);
        });
    }
};
