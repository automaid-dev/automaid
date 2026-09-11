<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Every order is created in `pending` status BEFORE the customer is
 * even redirected to the payment gateway (both Fiuu and GKash need an
 * order reference to build the checkout URL). If the customer never
 * completes payment — closes the app, loses signal, force-quits —
 * that order is left dangling in pending forever with nothing to ever
 * clean it up. OrderController::cancelPendingOrder handles the normal
 * case (customer taps the "X" on the payment page), but this command
 * is what catches everything that fails to reach that point at all.
 */
class CancelAbandonedOrders extends Command
{
    protected $signature = 'automaid:cancel-abandoned-orders {--minutes=60}';
    protected $description = 'Cancels orders still pending long after creation with no payment ever confirmed — cleans up abandoned checkout attempts.';

    public function handle()
    {
        $cutoff = Carbon::now()->subMinutes((int) $this->option('minutes'));

        $orders = Order::where('status', Order::PENDING)
            ->where('created_at', '<=', $cutoff)
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No abandoned pending orders found.');
            return 0;
        }

        $cancelledCount = 0;
        foreach ($orders as $order) {
            $order->status = Order::CANCELLED;
            $order->save();
            $cancelledCount++;

            // Same reasoning as OrderController::cancelPendingOrder —
            // the subscription history screen reads Subscription.status,
            // not Order.status, so this needs updating too or the
            // "Pending" badge would stay stuck even after the order
            // itself is cancelled.
            $subscription = $order->subscription;
            if ($subscription && $subscription->status === Subscription::PENDING) {
                $subscription->status = Subscription::CANCELLED;
                $subscription->save();
            }
        }

        $this->info("Cancelled {$cancelledCount} abandoned pending order(s) older than {$this->option('minutes')} minutes.");
        return 0;
    }
}
