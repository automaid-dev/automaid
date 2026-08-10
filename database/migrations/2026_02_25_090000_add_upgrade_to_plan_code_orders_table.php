<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tracks which plan a SUBSCRIPTION_UPGRADE order is upgrading the
     * customer's existing subscription to — the webhook needs this to
     * know what to actually change once the topup payment confirms,
     * since it only has the Order row to work from at that point, not
     * the original request.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('upgrade_to_plan_code', 20)->nullable()->after('order_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('upgrade_to_plan_code');
        });
    }
};
