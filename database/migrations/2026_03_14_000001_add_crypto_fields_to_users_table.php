<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('crypto_wallet')->nullable()->after('payout_details');
            $table->decimal('crz_token_balance', 18, 8)->default(0)->after('crypto_wallet');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['crypto_wallet', 'crz_token_balance']);
        });
    }
};
