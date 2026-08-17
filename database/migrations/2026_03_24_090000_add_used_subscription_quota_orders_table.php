<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks whether this specific order actually consumed a slot from
     * the customer's subscription quota (orders_used_current_cycle) at
     * booking time. Needed so a later cancellation can reliably know
     * whether to refund that slot — without this, there was no stored
     * signal distinguishing a quota-consuming order from a regular one.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('used_subscription_quota')->default(false)->after('is_pending_assign');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('used_subscription_quota');
        });
    }
};
