<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\AssignJob;

return new class extends Migration
{
    /**
     * The other half of the same gap already fixed for the rider side
     * of this order: merchant acceptance (code 21) was already marked
     * accepted from before the reassignment fix was deployed, but the
     * follow-up job the merchant actually needs to see next — code 22,
     * "await bag delivery" — was never created. Same root cause, same
     * fix pattern as the earlier rider-side migration for this order.
     *
     * Deliberately scoped to this one order, not a broad backfill —
     * same reasoning as before.
     */
    public function up(): void
    {
        $order = Order::find(1328);
        if (!$order) {
            return;
        }

        $alreadyHasCode22 = $order->assign_jobs()->where('code', '22')->exists();
        if ($alreadyHasCode22) {
            return;
        }

        $merchantUserId = 527;

        $status = OrderStatus::firstOrCreate([
            'order_id' => $order->id,
            'code' => '22',
        ]);

        AssignJob::firstOrCreate(
            [
                'code' => '22',
                'user_id' => $merchantUserId,
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
