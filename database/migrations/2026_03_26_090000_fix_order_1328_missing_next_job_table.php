<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\AssignJob;

return new class extends Migration
{
    /**
     * Targeted correction for the specific case diagnosed this session:
     * Order #1328 had its rider acceptance stage (code 11) correctly
     * marked done+accepted, but the follow-up job the rider actually
     * needs to see next — code 12, "ready for pickup" — was never
     * created at all. That's not a display bug; the assign_jobs row
     * genuinely doesn't exist. This creates it, using the same rider
     * (user_id=525) already recorded as having accepted code 11, so it
     * shows up correctly in their Today/Incoming dashboard immediately.
     *
     * Deliberately scoped to this one order — not a broad backfill —
     * since a wider pass risks creating jobs for orders where the
     * "next step" isn't actually code 12 (e.g. ones already further
     * along), which isn't something safe to infer in bulk.
     */
    public function up(): void
    {
        $order = Order::find(1328);
        if (!$order) {
            return;
        }

        $alreadyHasCode12 = $order->assign_jobs()->where('code', '12')->exists();
        if ($alreadyHasCode12) {
            return; // already fixed, or fixed manually since this was written
        }

        $riderUserId = 525;

        $status = OrderStatus::firstOrCreate([
            'order_id' => $order->id,
            'code' => '12',
        ]);

        AssignJob::firstOrCreate(
            [
                'code' => '12',
                'user_id' => $riderUserId,
                'order_id' => $order->id,
                'order_status_id' => $status->id,
            ],
            [
                'is_queue' => true,
                'is_accepted' => false,
            ]
        );
    }

    public function down(): void
    {
        //
    }
};
