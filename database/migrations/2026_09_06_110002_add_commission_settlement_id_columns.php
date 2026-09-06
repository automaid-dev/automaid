<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('commission_transactions', 'commission_settlement_id')) {
                $table->unsignedInteger('commission_settlement_id')->index()->nullable()->after('commission_id');
            }
        });

        Schema::table('commission_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('commission_payments', 'commission_settlement_id')) {
                $table->unsignedInteger('commission_settlement_id')->index()->nullable()->after('commission_transaction_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('commission_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('commission_transactions', 'commission_settlement_id')) {
                $table->dropColumn('commission_settlement_id');
            }
        });

        Schema::table('commission_payments', function (Blueprint $table) {
            if (Schema::hasColumn('commission_payments', 'commission_settlement_id')) {
                $table->dropColumn('commission_settlement_id');
            }
        });
    }
};
