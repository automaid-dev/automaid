<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\CustomerNotification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\PaymentGateway\GkashPaymentService;
use App\Services\PaymentGateway\RevenueMonsterService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Plan changes, the same on every gateway:
 *
 *  - Upgrade: pay the price difference now, new plan immediately, the full
 *    new price from the next renewal. Fiuu goes through its card checkout
 *    as before; GKash and Revenue Monster charge the saved card directly
 *    (chargeUpgrade, run when the app opens the result page).
 *  - Downgrade: nothing charged now; the customer keeps the current plan
 *    until the paid period ends, and the renewal job bills and switches to
 *    the lower plan from the next cycle (subscriptions.scheduled_plan_code).
 */
class SubscriptionPlanChangeService
{
    public const TOKEN_GATEWAYS = ['gkash', 'revenuemonster'];

    public static function nextBillingDate(Subscription $subscription): ?Carbon
    {
        $date = $subscription->recurring_active?->next_payment_date ?? $subscription->renew_at ?? $subscription->end_date;
        return $date ? Carbon::parse($date)->startOfDay() : null;
    }

    public function scheduleDowngrade(Subscription $subscription, string $planCode): ?Carbon
    {
        $subscription->scheduled_plan_code = $planCode;
        $subscription->save();
        Log::info('Subscription downgrade scheduled', ['subscription_id' => $subscription->id, 'to' => $planCode]);
        return self::nextBillingDate($subscription);
    }

    public function cancelScheduled(Subscription $subscription): void
    {
        $subscription->scheduled_plan_code = null;
        $subscription->save();
    }

    public static function resultKey(int $orderId): string
    {
        return substr(hash_hmac('sha256', 'plan-change:' . $orderId, (string) config('app.key')), 0, 32);
    }

    /**
     * Charge an upgrade on the saved card (GKash / Revenue Monster).
     * Runs once per order; a repeat call just reports the current state.
     *
     * @return string paid | failed | pending
     */
    public function chargeUpgrade(Order $order): string
    {
        return Cache::lock('plan-change-' . $order->id, 90)->block(60, function () use ($order) {
            $order->refresh();
            if ($order->status === Order::PAID) {
                return 'paid';
            }
            if ($order->status === Order::CANCELLED) {
                return 'failed';
            }
            if (Cache::has('plan-change-attempted-' . $order->id)) {
                return 'pending';
            }

            $subscription = $order->user?->subscribe;
            $recurring = $subscription?->recurring_active;
            $gateway = $subscription?->payment_gateway;
            if (!$subscription || !$recurring?->token || !in_array($gateway, self::TOKEN_GATEWAYS, true) || $order->order_type !== Order::SUBSCRIPTION_UPGRADE) {
                Log::error('Upgrade charge: no saved card for this subscription', ['order_id' => $order->id]);
                $order->cancelUnpaidCheckout();
                return 'failed';
            }
            Cache::put('plan-change-attempted-' . $order->id, true, now()->addDays(7));

            $amount = (float) $order->grand_total;
            if ($gateway === 'gkash') {
                $r = (new GkashPaymentService())->chargeRecurring($recurring->token, $amount, (string) $order->id, 'MONTHLY');
                $outcome = $r['outcome'];
                $data = $r['raw'];
                $channel = $r['raw']['PaymentType'] ?? 'CARD';
                $newToken = $r['epkey'] ?? null; // GKash issues a new token per charge
                $reason = trim(($r['status'] ?? '') . ' ' . ($r['description'] ?? ''));
                $method = Payment::GKASH;
            } else {
                $r = (new RevenueMonsterService())->chargeCustomer($recurring->token, $amount, 'AutoMaid plan upgrade', 'Upgrade order #' . $order->id);
                $outcome = $r['outcome'];
                $data = $r['raw'];
                $channel = 'CARD';
                $newToken = null;
                $reason = (string) $r['reason'];
                $method = Payment::REVENUE_MONSTER;
            }

            if ($outcome === 'paid') {
                if ($newToken) {
                    $recurring->token = $newToken;
                    $recurring->save();
                }
                $this->completeUpgrade($order, $data, $method, $channel);
                return 'paid';
            }
            if ($outcome === 'failed') {
                Cache::forget('plan-change-attempted-' . $order->id);
                Log::warning('Upgrade charge declined', ['order_id' => $order->id, 'reason' => $reason]);
                $order->cancelUnpaidCheckout();
                return 'failed';
            }

            Log::warning('Upgrade charge result unknown', ['order_id' => $order->id, 'reason' => $reason]);
            try {
                (new TelegramService())->notifyAdmin("⚠️ <b>Plan upgrade payment pending</b>\nOrder #{$order->id} (subscription #{$subscription->id}, RM" . number_format($amount, 2) . ") on "
                    . ($gateway === 'gkash' ? 'GKash' : 'Revenue Monster') . ' came back as "' . e($reason) . '". Check the gateway portal; the plan was not changed and it will not be charged again automatically.');
            } catch (\Throwable $e) {
            }
            return 'pending';
        });
    }

    /**
     * Paid upgrade → switch plan now. Same result as the Fiuu webhook's
     * upgrade branch, plus clearing any scheduled downgrade.
     */
    public function completeUpgrade(Order $order, array $data, string $paymentMethod, ?string $channel = null): void
    {
        DB::transaction(function () use ($order, $data, $paymentMethod, $channel) {
            $payment = $order->payment;
            $transaction = Transaction::firstOrCreate(
                ['order_id' => $order->id, 'payment_id' => $payment?->id],
                ['date' => now(), 'type' => $order->order_type, 'amount' => $order->grand_total, 'status' => Transaction::PAID]
            );
            if ($payment) {
                $payment->data = json_encode($data);
                $payment->payment_method = $paymentMethod;
                $payment->status = Payment::PAID;
                $payment->channel = $channel;
                $payment->is_paid = true;
                $payment->paid_at = now();
                $payment->save();
            }
            $order->status = Order::PAID;
            $order->save();

            $subscription = $order->user?->subscribe;
            if ($subscription && $order->upgrade_to_plan_code) {
                $subscription->plan_code = $order->upgrade_to_plan_code;
                $subscription->scheduled_plan_code = null;
                $subscription->orders_used_current_cycle = 0;
                $subscription->save();
            }
            Activity::firstOrCreate([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'transaction_id' => $transaction->id,
                'user_type' => 'customer',
                'title' => 'Subscription Upgrade',
                'status' => Activity::ACTIVE,
            ]);
        });

        $user = $order->user;
        if ($user) {
            $plan = Subscription::planLabel((string) $order->upgrade_to_plan_code);
            try {
                (new OneSignalService())->notifyUser(
                    $user,
                    CustomerNotification::SUBSCRIPTION_RENEWED,
                    'Auto Maid: Your plan has been upgraded',
                    "You're now on the {$plan} plan.",
                    '<p>Hi ' . e($user->name) . ',</p><p>Your subscription has been upgraded to the <b>' . e($plan) . '</b> plan. We charged RM' . number_format((float) $order->grand_total, 2) . ' (the difference for this billing period) to your saved card.</p><p>— AutoMaid</p>',
                    $order->id,
                );
            } catch (\Throwable $e) {
                Log::warning('Upgrade notice failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }
        Log::info('Subscription upgraded', ['order_id' => $order->id, 'plan' => $order->upgrade_to_plan_code]);
    }
}
