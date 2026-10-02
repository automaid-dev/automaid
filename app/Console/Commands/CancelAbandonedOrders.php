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
    protected $signature = 'automaid:cancel-abandoned-orders {--minutes=60} {--dry-run : List pending subscriptions and what would happen, change nothing}';
    protected $description = 'Cancels orders still pending long after creation with no payment ever confirmed — cleans up abandoned checkout attempts.';

    public function handle()
    {
        $cutoff = Carbon::now()->subMinutes((int) $this->option('minutes'));

        if ($this->option('dry-run')) {
            return $this->dryRun($cutoff);
        }

        // 1. Checkouts still pending long after creation.
        $orders = Order::where('status', Order::PENDING)
            ->where('created_at', '<=', $cutoff)
            ->get();
        foreach ($orders as $order) {
            $order->cancelUnpaidCheckout();
        }

        // 2. Repair checkouts cancelled before cancelUnpaidCheckout()
        //    existed: the order was cancelled but its Payment stayed
        //    "pending" (and a subscription's bag stayed active) — e.g.
        //    GKash sign-ups refused with "Recurring not available".
        $leftovers = Order::where('status', Order::CANCELLED)
            ->where(function ($q) {
                $q->whereHas('payment', fn ($p) => $p->where('status', \App\Models\Payment::PENDING)->where(fn ($x) => $x->whereNull('is_paid')->orWhere('is_paid', false)))
                  ->orWhereHas('subscription', fn ($s) => $s->where('status', Subscription::PENDING));
            })
            ->get();
        foreach ($leftovers as $order) {
            $order->cancelUnpaidCheckout();
        }

        // 3. Any other subscription still "pending" long after sign-up
        //    whose order is in some other state (or missing) and that was
        //    never paid. A pending subscription whose payment WAS taken is
        //    left alone and reported — that needs manual activation.
        $stray = 0;
        $needsReview = [];
        foreach ($this->strayPendingSubscriptions($cutoff) as [$subscription, $order, $paid]) {
            if ($paid) {
                $needsReview[] = $subscription->id;
                continue;
            }
            if ($order) {
                $order->cancelUnpaidCheckout();
            }
            $subscription->refresh();
            if ($subscription->status === Subscription::PENDING) {
                $subscription->status = Subscription::CANCELLED;
                $subscription->save();
            }
            $stray++;
        }

        $this->info("Cancelled {$orders->count()} abandoned pending order(s) older than {$this->option('minutes')} minutes; repaired {$leftovers->count()} previously cancelled checkout(s); cancelled {$stray} other unpaid pending subscription(s).");
        if ($needsReview) {
            $msg = 'Pending subscription(s) with a PAID payment — not cancelled, please activate or refund manually: #' . implode(', #', $needsReview);
            $this->warn($msg);
            \Illuminate\Support\Facades\Log::warning($msg);
        }
        return 0;
    }

    /**
     * Pending subscriptions older than the cutoff whose order is not
     * plain "pending"/"cancelled" (those are handled above).
     *
     * @return array<int, array{0: Subscription, 1: ?Order, 2: bool}>
     */
    protected function strayPendingSubscriptions(Carbon $cutoff): array
    {
        $rows = [];
        $subs = Subscription::where('status', Subscription::PENDING)
            ->where('created_at', '<=', $cutoff)
            ->get();
        foreach ($subs as $subscription) {
            $order = $subscription->order;
            if ($order && in_array($order->status, [Order::PENDING, Order::CANCELLED], true)) {
                continue;
            }
            $payment = $order?->payment ?? $subscription->payment;
            $paid = ($order && $order->status === Order::PAID)
                || ($payment && ($payment->is_paid || $payment->status === \App\Models\Payment::PAID));
            $rows[] = [$subscription, $order, (bool) $paid];
        }
        return $rows;
    }

    protected function dryRun(Carbon $cutoff): int
    {
        $subs = Subscription::where('status', Subscription::PENDING)->orderBy('id')->get();
        if ($subs->isEmpty()) {
            $this->info('No pending subscriptions.');
            return 0;
        }
        $rows = [];
        foreach ($subs as $s) {
            $order = $s->order;
            $payment = $order?->payment ?? $s->payment;
            $paid = ($order && $order->status === Order::PAID) || ($payment && ($payment->is_paid || $payment->status === \App\Models\Payment::PAID));
            $old = $s->created_at && $s->created_at->lte($cutoff);
            $action = $paid ? 'LEAVE (payment taken — review manually)'
                : (!$old ? 'wait (newer than cutoff)' : 'cancel order, payment, subscription, bag');
            $rows[] = [$s->id, $s->user_id, $s->payment_gateway ?? '-', $s->created_at, $order?->id ?? '-', $order?->status ?? '(no order)', $payment?->status ?? '-', $payment?->is_paid ? 'yes' : 'no', $action];
        }
        $this->table(['Sub #', 'User', 'Gateway', 'Created', 'Order #', 'Order status', 'Payment', 'Paid?', 'Would do'], $rows);
        $this->line('Nothing changed (dry run). Run without --dry-run to apply.');
        return 0;
    }
}
