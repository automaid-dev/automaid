<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment gateway service charge (% per gateway, set by admin; 0 = off).
 * See App\Support\GatewayFee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'fiuu_service_charge_percent')) {
                $table->decimal('fiuu_service_charge_percent', 5, 2)->default(0);
            }
            if (!Schema::hasColumn('settings', 'gkash_service_charge_percent')) {
                $table->decimal('gkash_service_charge_percent', 5, 2)->default(0);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'gateway_fee')) {
                // Included in grand_total; kept separately for receipts/reports.
                $table->decimal('gateway_fee', 18, 2)->default(0)->after('grand_total');
            }
            if (!Schema::hasColumn('orders', 'gateway_fee_percent')) {
                $table->decimal('gateway_fee_percent', 5, 2)->default(0)->after('gateway_fee');
            }
        });

        if (Schema::hasTable('subscription_renewal_charges') && !Schema::hasColumn('subscription_renewal_charges', 'gateway_fee')) {
            Schema::table('subscription_renewal_charges', function (Blueprint $table) {
                // Included in amount.
                $table->decimal('gateway_fee', 18, 2)->default(0)->after('amount');
            });
        }
    }

    public function down(): void
    {
        foreach (['settings' => ['fiuu_service_charge_percent', 'gkash_service_charge_percent'], 'orders' => ['gateway_fee', 'gateway_fee_percent'], 'subscription_renewal_charges' => ['gateway_fee']] as $t => $cols) {
            if (!Schema::hasTable($t)) continue;
            Schema::table($t, function (Blueprint $table) use ($t, $cols) {
                foreach ($cols as $c) {
                    if (Schema::hasColumn($t, $c)) $table->dropColumn($c);
                }
            });
        }
    }
};
