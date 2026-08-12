<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Resets is_check_queue=false on order_statuses for orders that are
     * still is_pending_assign=true — these were permanently locked out
     * of ever being retried by the auto-assign cron (see the fix to
     * AssignOrderToRiderAndMerchant.php in this same patch). This gives
     * them a fresh shot at being picked up automatically on the very
     * next cron tick (it runs every minute), if a rider/merchant is now
     * on duty, without needing manual admin reassignment.
     */
    public function up(): void
    {
        DB::table('order_statuses')
            ->whereIn('code', ['11', '21'])
            ->where('is_done', false)
            ->whereIn('order_id', function ($query) {
                $query->select('id')
                    ->from('orders')
                    ->where('is_pending_assign', true);
            })
            ->update(['is_check_queue' => false]);
    }

    public function down(): void
    {
        //
    }
};
