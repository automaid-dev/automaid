<?php

namespace App\Services;

use App\Mail\RenewalSubscriptionEmail;
use App\Models\Activity;
use App\Models\CustomerNotification;
use App\Models\PaymentRecurring;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionRenewalCharge;
use App\Models\Transaction;
use App\Services\PaymentGateway\FiuuPaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Monthly Fiuu subscription renewal, in two halves:
 *
 *  1. chargeFiuu()         — nightly job sends a token charge (unique OrderID
 *                            "SR..." per attempt, current plan price) and
 *                            records it as ACCEPTED. Nothing is extended yet.
 *  2. handleFiuuCallback() — Fiuu POSTs the real result to our callback URL.
 *                            Only status 00 extends the subscription; 11
 *                            records a failure (retried next night, up to
 *                            SubscriptionRenewalCharge::MAX_ATTEMPTS).
 */
class SubscriptionRenewalService
{
    /** An accepted charge with no callback after this long is treated as failed. */
    const STALE_ACCEPTED_HOURS = 48;

    /**
     * @return array{action: string, message: string, charge_id?: int}
     */
    public function chargeFiuu(PaymentRecurring $recurring, bool $dryRun = false): array
    {
        $subscription = $recurring->subscription;
        $order = $recurring->payment?->order;
        $user = $order?->user;
        if (!$subscription || !$order || !$user || !$recurring->token) {
            return ['action' => 'skipped', 'message' => 'Missing subscription, order, user or token.'];
        }

        $cycle = Carbon::parse($recurring->next_payment_date)->toDateString();
        $charges = SubscriptionRenewalCharge::where('subscription_id', $subscription->id)
            ->whereDate('cycle_date', $cycle)->get();

        if ($charges->contains('status', SubscriptionRenewalCharge::PAID)) {
            return ['action' => 'skipped', 'message' => "Cycle {$cycle} already paid."];
        }

        // Waiting on Fiuu's callback for an earlier attempt? Don't charge again —
        // unless it has been silent for too long, in which case count it as failed.
        foreach ($charges->whereIn('status', [SubscriptionRenewalCharge::ACCEPTED, SubscriptionRenewalCharge::PENDING]) as $open) {
            if ($open->created_at->gt(now()->subHours(self::STALE_ACCEPTED_HOURS))) {
                return ['action' => 'skipped', 'message' => "Waiting for Fiuu's result on {$open->reference}."];
            }
            if (!$dryRun) {
                $this->markFailed($open, 'No result received from Fiuu within ' . self::STALE_ACCEPTED_HOURS . ' hours.', null, false);
            }
        }

        $failed = SubscriptionRenewalCharge::where('subscription_id', $subscription->id)
            ->whereDate('cycle_date', $cycle)->where('status', SubscriptionRenewalCharge::FAILED)->count();
        if ($failed >= SubscriptionRenewalCharge::MAX_ATTEMPTS) {
            return ['action' => 'skipped', 'message' => "Gave up on cycle {$cycle} after {$failed} failed attempts."];
        }

        // Current plan price (so an upgrade is billed at the new price), not
        // the original sign-up order's amount.
        $setting = Setting::find(1);
        $amount = $setting ? $subscription->planPrice($setting) : 0;
        if ($amount < 1) {
            $amount = (float) $order->grand_total; // fallback; Fiuu minimum is above RM1.00
        }

        $attempt = $failed + 1;
        $reference = SubscriptionRenewalCharge::REFERENCE_PREFIX . $subscription->id . '-' . Carbon::parse($cycle)->format('Ymd') . '-' . $attempt;
        $fiuu = (new FiuuPaymentService())->forMerchant($recurring->merchant_id);

        if ($dryRun) {
            return ['action' => 'dry-run', 'message' => sprintf(
                'Would charge RM%s (%s plan) on Fiuu account %s, reference %s, token ****%s.',
                number_format($amount, 2), $subscription->plan_code ?? '-', $fiuu->getMerchantId(), $reference, substr($recurring->token, -4)
            )];
        }

        $charge = SubscriptionRenewalCharge::create([
            'subscription_id' => $subscription->id,
            'payment_recurring_id' => $recurring->id,
            'order_id' => $order->id,
            'gateway' => 'fiuu',
            'merchant_id' => $fiuu->getMerchantId(),
            'reference' => $reference,
            'cycle_date' => $cycle,
            'attempt' => $attempt,
            'plan_code' => $subscription->plan_code,
            'amount' => $amount,
            'status' => SubscriptionRenewalCharge::PENDING,
        ]);

        $result = $fiuu->chargeToken(
            $recurring->token,
            $reference,
            $amount,
            $order->billing_name ?: $user->name,
            $order->billing_email ?: $user->email,
            $order->billing_phone ?: $user->mobile_no,
            (string) $user->id,
            'AutoMaid ' . ucfirst((string) $subscription->plan_code) . ' subscription',
        );

        $charge->request_response = json_encode($result['raw']);
        $charge->tran_id = $result['tran_id'];

        if ($result['accepted']) {
            $charge->status = SubscriptionRenewalCharge::ACCEPTED;
            $charge->save();
            Log::info('Fiuu renewal charge accepted, waiting for callback', ['reference' => $reference, 'tran_id' => $result['tran_id']]);
            return ['action' => 'accepted', 'message' => "Charge {$reference} accepted by Fiuu; waiting for result.", 'charge_id' => $charge->id];
        }

        $charge->save();
        $this->markFailed($charge, $result['reason'] ?: 'Rejected by Fiuu');
        return ['action' => 'failed', 'message' => "Charge {$reference} rejected: " . ($result['reason'] ?: 'unknown'), 'charge_id' => $charge->id];
    }

    /**
     * Fiuu's recurring result callback (nbcb=1). Returns the body Fiuu
     * expects back. Safe to receive more than once.
     */
    public function handleFiuuCallback(array $data): string
    {
        $ack = 'CBTOKEN:MPSTATOK';
        $reference = $data['orderid'] ?? null;

        if (!(new FiuuPaymentService())->verifyRecurringCallback($data)) {
            Log::warning('Fiuu renewal callback: invalid signature, ignored', ['orderid' => $reference]);
            return $ack;
        }

        $charge = SubscriptionRenewalCharge::where('reference', $reference)->first();
        if (!$charge) {
            Log::warning('Fiuu renewal callback: unknown reference', ['orderid' => $reference]);
            return $ack;
        }

        if ($charge->status === SubscriptionRenewalCharge::PAID) {
            return $ack; // duplicate callback
        }

        $charge->callback_data = json_encode($data);
        $charge->tran_id = $data['tranID'] ?? $charge->tran_id;
        $status = (string) ($data['status'] ?? '');

        if ($status === '00') {
            if (number_format((float) ($data['amount'] ?? 0), 2, '.', '') !== number_format((float) $charge->amount, 2, '.', '')) {
                Log::error('Fiuu renewal callback: amount differs from what we charged', ['reference' => $reference, 'charged' => $charge->amount, 'callback' => $data['amount'] ?? null]);
            }
            $this->markPaid($charge, $data);
        } elseif ($status === '22') {
            $charge->save(); // still pending at the bank; wait for the next callback
        } else {
            $charge->save();
            $reason = trim(($data['error_code'] ?? '') . ' ' . ($data['error_desc'] ?? '')) ?: 'Card charge failed';
            $this->markFailed($charge, $reason, $data);
        }

        return $ack;
    }

    protected function markPaid(SubscriptionRenewalCharge $charge, array $data): void
    {
        DB::transaction(function () use ($charge, $data) {
            $recurring = $charge->paymentRecurring;
            $subscription = $charge->subscription;
            $payment = $recurring?->payment;
            $order = $payment?->order;

            $charge->status = SubscriptionRenewalCharge::PAID;
            $charge->result_at = now();
            $charge->save();

            if (!$recurring || !$subscription || !$order) {
                Log::error('Fiuu renewal paid but subscription records are missing', ['charge_id' => $charge->id]);
                return;
            }

            $transaction = Transaction::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'type' => Transaction::SUBSCRIPTION_RENEWAL,
                    'date' => now()->toDateString(),
                ],
                ['amount' => $charge->amount, 'status' => Transaction::PAID]
            );

            // Anchored on the billing date, not "today", so a retry a day
            // late doesn't shift the customer's billing date.
            $next = Carbon::parse($charge->cycle_date)->addMonth();

            PaymentRecurring::firstOrCreate(
                [
                    'payment_id' => $recurring->payment_id,
                    'subscription_id' => $subscription->id,
                    'transaction_id' => $transaction->id,
                ],
                [
                    'token' => $recurring->token,
                    'merchant_id' => $recurring->merchant_id,
                    'payment_date' => now()->toDateString(),
                    'next_payment_date' => $next->toDateString(),
                    'status' => PaymentRecurring::SUBSCRIPTION_RENEWAL,
                    'status_payment' => PaymentRecurring::PAID,
                    'data' => json_encode($data),
                    'amount' => $charge->amount,
                    'is_paid' => true,
                    'paid_at' => now(),
                    'cc_brand' => $recurring->cc_brand,
                    'cc_last_four' => $recurring->cc_last_four,
                    'cc_type' => $recurring->cc_type,
                ]
            );

            $recurring->status_payment = PaymentRecurring::COMPLETE;
            $recurring->save();

            $subscription->status = Subscription::ACTIVE;
            $subscription->end_date = $next;
            $subscription->renew_at = $next;
            $subscription->orders_used_current_cycle = 0;
            $subscription->save();

            Activity::firstOrCreate([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'transaction_id' => $transaction->id,
                'user_type' => 'customer',
                'title' => 'Subscription Renewal',
                'status' => Activity::ACTIVE,
            ]);

            DB::afterCommit(function () use ($order, $charge, $next) {
                $user = $order->user;
                $subject = 'Auto Maid: Your subscription renewal is successful';
                try {
                    $html = (new RenewalSubscriptionEmail($user->name, $subject, $order))->render();
                } catch (\Throwable $e) {
                    $html = '<p>Hi ' . e($user->name) . ',</p><p>Your AutoMaid subscription has been renewed (RM' . number_format($charge->amount, 2) . ').</p>';
                }
                (new OneSignalService())->notifyUser(
                    $user,
                    CustomerNotification::SUBSCRIPTION_RENEWED,
                    $subject,
                    'Your subscription has been renewed until ' . $next->format('j M Y') . '.',
                    $html,
                    $order->id,
                );
            });
        });

        Log::info('Fiuu renewal paid', ['reference' => $charge->reference, 'tran_id' => $charge->tran_id]);
    }

    protected function markFailed(SubscriptionRenewalCharge $charge, string $reason, ?array $data = null, bool $notify = true): void
    {
        $charge->status = SubscriptionRenewalCharge::FAILED;
        $charge->reason = mb_substr($reason, 0, 300);
        $charge->result_at = now();
        if ($data) {
            $charge->callback_data = json_encode($data);
        }
        $charge->save();

        $failed = SubscriptionRenewalCharge::where('subscription_id', $charge->subscription_id)
            ->whereDate('cycle_date', $charge->cycle_date)->where('status', SubscriptionRenewalCharge::FAILED)->count();
        $finalAttempt = $failed >= SubscriptionRenewalCharge::MAX_ATTEMPTS;

        Log::warning('Fiuu renewal failed', ['reference' => $charge->reference, 'reason' => $reason, 'attempt' => $failed, 'final' => $finalAttempt]);

        $subscription = $charge->subscription;
        if ($finalAttempt) {
            // Stop the nightly job retrying this token; benefits already
            // stopped when the paid period ended.
            if ($charge->paymentRecurring) {
                $charge->paymentRecurring->status = PaymentRecurring::INACTIVE;
                $charge->paymentRecurring->save();
            }
            if ($subscription) {
                $subscription->status = Subscription::INACTIVE;
                $subscription->save();
            }
        }

        $user = $subscription?->user;
        if (!$notify || !$user) {
            return;
        }

        $amount = 'RM' . number_format($charge->amount, 2);
        if ($finalAttempt) {
            $subject = 'Auto Maid: Your subscription could not be renewed';
            $body = "We couldn't charge {$amount} to your card after {$failed} attempts, so your subscription has ended. You can subscribe again anytime in the app.";
        } else {
            $subject = 'Auto Maid: Subscription payment failed';
            $body = "We couldn't charge {$amount} to your card for your subscription renewal. We'll try again tomorrow (attempt {$failed} of " . SubscriptionRenewalCharge::MAX_ATTEMPTS . "). Please make sure your card can be charged.";
        }

        try {
            (new OneSignalService())->notifyUser(
                $user,
                CustomerNotification::SUBSCRIPTION_RENEWAL_FAILED,
                $subject,
                $body,
                '<p>Hi ' . e($user->name) . ',</p><p>' . e($body) . '</p><p>— AutoMaid</p>',
                $charge->order_id,
            );
        } catch (\Throwable $e) {
            Log::error('Could not notify customer of failed renewal', ['charge_id' => $charge->id, 'error' => $e->getMessage()]);
        }
    }
}
