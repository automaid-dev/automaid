<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Targeted, one-time correction for the specific case diagnosed
     * this session: Order #1327 (user #522) was placed under an active
     * subscription but never got counted, because the webhook that
     * actually completes a subscribed-but-not-free order had no quota
     * tracking at all (fixed alongside this migration, in
     * FiuuController.php). This applies the correction that order
     * should have received at the time.
     *
     * Deliberately scoped to this one order rather than a broad
     * "backfill everyone" pass — a wider backfill risks over- or
     * under-counting for orders where it's genuinely ambiguous whether
     * quota should have applied (e.g. subscription status changed
     * between order placement and now). This one case is confirmed.
     */
    public function up(): void
    {
        $order = DB::table('orders')->where('id', 1327)->first();
        if (!$order || $order->used_subscription_quota) {
            return; // already corrected, or order no longer exists — nothing to do
        }

        DB::table('orders')->where('id', 1327)->update(['used_subscription_quota' => true]);

        DB::table('subscriptions')->where('id', 323)->increment('orders_used_current_cycle');
    }

    public function down(): void
    {
        //
    }
};
