<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `plan_code` records which package (bronze/silver/platinum) this
     * subscription is on — see Subscription::BRONZE/SILVER/PLATINUM.
     * Nullable so existing subscriptions created before plans existed
     * keep working under the old flat unlimited-free-bag behaviour.
     *
     * `orders_used_current_cycle` counts bookings placed under this
     * subscription since the last renewal — reset to 0 on each successful
     * renewal (see CheckNextPaymentSubscription). Compared against the
     * plan's order quota (settings.subscription_bronze_orders /
     * subscription_silver_orders; platinum has no quota = unlimited) to
     * decide whether a new booking still gets the free-first-bag benefit.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('plan_code', 20)->nullable()->after('status');
            $table->unsignedInteger('orders_used_current_cycle')->default(0)->after('plan_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['plan_code', 'orders_used_current_cycle']);
        });
    }
};
