<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revenue Monster: the RM order id of each checkout, and its own
 * service charge % (see App\Support\GatewayFee).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'gateway_reference')) {
                $table->string('gateway_reference', 64)->nullable()->index()->after('payment_gateway');
            }
        });
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'revenuemonster_service_charge_percent')) {
                $table->decimal('revenuemonster_service_charge_percent', 5, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'gateway_reference')) $table->dropColumn('gateway_reference');
        });
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'revenuemonster_service_charge_percent')) $table->dropColumn('revenuemonster_service_charge_percent');
        });
    }
};
