<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curzzos', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->default(null)->after('personality');
            $table->string('currency', 3)->nullable()->default('PHP')->after('price');
            $table->enum('billing_type', ['one_time', 'monthly'])->nullable()->default('one_time')->after('currency');
            $table->unsignedTinyInteger('affiliate_commission_rate')->nullable()->default(null)->after('billing_type');
        });
    }

    public function down(): void
    {
        Schema::table('curzzos', function (Blueprint $table) {
            $table->dropColumn(['price', 'currency', 'billing_type', 'affiliate_commission_rate']);
        });
    }
};
