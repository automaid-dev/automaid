<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the per-plan price + order-quota "variables" the admin edits in
     * Settings (General Settings > Subscription Fees/Discounts). The
     * existing `subscription_price` column is left in place untouched for
     * backward compatibility with subscriptions created before plans
     * existed; new subscriptions use the matching plan price below.
     *
     * Free-bag-per-order behaviour (only the first bag is free, per the
     * requested package rules) is already handled by the existing
     * `total_bag_free_wash` / `total_bag_free_delivery` settings — set
     * those to 1 each; no new columns needed for that part.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('subscription_bronze_price', 18, 5)->nullable()->after('subscription_price');
            $table->unsignedInteger('subscription_bronze_orders')->nullable()->after('subscription_bronze_price');

            $table->decimal('subscription_silver_price', 18, 5)->nullable()->after('subscription_bronze_orders');
            $table->unsignedInteger('subscription_silver_orders')->nullable()->after('subscription_silver_price');

            $table->decimal('subscription_platinum_price', 18, 5)->nullable()->after('subscription_silver_orders');
            // No subscription_platinum_orders column — platinum is unlimited
            // orders per cycle by design; a null quota is always treated as
            // unlimited throughout the app (see Subscription::hasOrderQuotaRemaining()).
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_bronze_price',
                'subscription_bronze_orders',
                'subscription_silver_price',
                'subscription_silver_orders',
                'subscription_platinum_price',
            ]);
        });
    }
};
