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
use App\Services\PaymentGateway\GkashPaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Monthly subscription renewal on a stored card token.
 *
 * GKash (chargeGkash): the charge result normally comes straight back.
 *   88 extends the subscription, 66 records a failure (retried next night,
 *   up to MAX_ATTEMPTS, customer notified each time). 11 "Pending", a
 *   timeout or an unreadable reply means the card MAY have been charged, so
 *   the charge is left PENDING, admin is alerted, and the job will NOT
 *   charge that cycle again until GKash's callback or an admin settles it
 *   (php artisan automaid:gkash-test-renewal {id} --resolve=REF --as=paid|failed).
 *
 * Fiuu, in two halves:
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
     * GKash monthly charge for one due PaymentRecurring row.
     *
     * @return array{action: string, message: string, charge_id?: int}
     */
    public function chargeGkash(PaymentRecurring $recurring, bool $dryRun = false): array
    {
        $subscription = $recurring->subscription;
        $order = $recurring->payment?->order;
        $user = $order?->user;
        if (!$subscription || !$order || !$user || !$recurring->token) {
            return ['action' => 'skipped', 'message' => 'Missing subscription, order, user or card token.'];
        }

        $cycle = Carbon::parse($recurring->next_payment_date)->toDateString();
        $charges = SubscriptionRenewalCharge::where('subscription_id', $subscription->id)
            ->whereDate('cycle_date', $cycle)->get();

        if ($charges->contains('status', SubscriptionRenewalCharge::PAID)) {
            return ['action' => 'skipped', 'message' => "Cycle {$cycle} already paid."];
        }

        // An unsettled charge may already have taken the money — never
        // charge the same cycle again until it is resolved.
        $open = $charges->first(fn ($c) => in_array($c->status, [SubscriptionRenewalCharge::PENDING, SubscriptionRenewalCharge::ACCEPTED], true));
        if ($open) {
            return ['action' => 'skipped', 'message' => "Charge {$open->reference} is still pending at GKash (since {$open->created_at}). Check the GKash portal, then run: php artisan automaid:gkash-test-renewal {$subscription->id} --resolve={$open->reference} --as=paid|failed"];
        }

        $failed = $charges->where('status', SubscriptionRenewalCharge::FAILED)->count();
        if ($failed >= SubscriptionRenewalCharge::MAX_ATTEMPTS) {
            return ['action' => 'skipped', 'message' => "Gave up on cycle {$cycle} after {$failed} failed attempts."];
        }

        $amount = $this->currentPlanAmount($subscription, $order);
        $attempt = $failed + 1;
        $reference = SubscriptionRenewalCharge::REFERENCE_PREFIX . $subscription->id . '-' . Carbon::parse($cycle)->format('Ymd') . '-' . $attempt;

        if ($dryRun) {
            return ['action' => 'dry-run', 'message' => sprintf(
                'Would charge RM%s (%s plan) on GKash, reference %s, card token %s…, for the cycle due %s.',
                number_format($amount, 2), $subscription->plan_code ?? '-', $reference, substr($recurring->token, 0, 6), $cycle
            )];
        }

        // Saved as PENDING before calling GKash: if anything dies mid-call,
        // the row blocks a second charge instead of allowing one.
        $charge = SubscriptionRenewalCharge::create([
            'subscription_id' => $subscription->id,
            'payment_recurring_id' => $recurring->id,
            'order_id' => $order->id,
            'gateway' => 'gkash',
            'merchant_id' => config('services.gkash.merchant_id'),
            'reference' => $reference,
            'cycle_date' => $cycle,
            'attempt' => $attempt,
            'plan_code' => $subscription->plan_code,
            'amount' => $amount,
            'status' => SubscriptionRenewalCharge::PENDING,
        ]);

        $result = (new GkashPaymentService())->chargeRecurring($recurring->token, $amount, $reference, 'MONTHLY');

        $charge->request_response = json_encode($result['raw']);
        $charge->tran_id = $result['raw']['POID'] ?? null;
        $charge->save();

        if ($result['outcome'] === 'paid') {
            $this->markPaid($charge, $result['raw'], $result['epkey']);
            return ['action' => 'paid', 'message' => "Charged RM" . number_format($amount, 2) . " ({$reference}, POID {$charge->tran_id}).", 'charge_id' => $charge->id];
        }

        $reason = trim(($result['status'] ?? '') . ' ' . ($result['description'] ?? '')) ?: 'Rejected by GKash';

        if ($result['outcome'] === 'failed') {
            $this->markFailed($charge, $reason);
            return ['action' => 'failed', 'message' => "Charge {$reference} failed: {$reason}", 'charge_id' => $charge->id];
        }

        $charge->reason = mb_substr($reason, 0, 300);
        $charge->save();
        $this->notifyAdmin(
            "Subscription renewal pending at GKash",
            "Subscription #{$subscription->id} (user #{$user->id}, {$user->email}): charge {$reference} for RM" . number_format($amount, 2)
            . " came back as \"{$reason}\". The customer may or may not have been charged, so it will not be retried automatically.\n"
            . "Check {$reference} in the GKash merchant portal, then run on the server:\n"
            . "php artisan automaid:gkash-test-renewal {$subscription->id} --resolve={$reference} --as=paid   (or --as=failed)"
        );
        return ['action' => 'pending', 'message' => "Charge {$reference} is pending at GKash ({$reason}); admin alerted, not retrying.", 'charge_id' => $charge->id];
    }

    /**
     * GKash callback for a renewal charge (cartid "SR..."), e.g. the final
     * result of a charge that first came back "11 - Pending". The caller
     * has already verified the signature. Safe to receive more than once.
     */
    public function handleGkashCallback(array $data): string
    {
        $charge = SubscriptionRenewalCharge::where('reference', $data['cartid'] ?? null)->first();
        if (!$charge) {
            Log::warning('GKash renewal callback: unknown reference', ['cartid' => $data['cartid'] ?? null]);
            return 'OK';
        }
        if (in_array($charge->status, [SubscriptionRenewalCharge::PAID, SubscriptionRenewalCharge::FAILED], true)) {
            return 'OK'; // already settled (the synchronous reply usually gets there first)
        }

        $charge->callback_data = json_encode($data);
        $charge->tran_id = $data['POID'] ?? $charge->tran_id;
        $status = (string) ($data['status'] ?? '');

        if (str_starts_with($status, '88')) {
            if (number_format((float) ($data['amount'] ?? 0), 2, '.', '') !== number_format((float) $charge->amount, 2, '.', '')) {
                Log::error('GKash renewal callback: amount differs from what we charged', ['reference' => $charge->reference, 'charged' => $charge->amount, 'callback' => $data['amount'] ?? null]);
            }
            $charge->save();
            $this->markPaid($charge, $data, $data['epkey'] ?? null);
        } elseif (str_starts_with($status, '66')) {
            $charge->save();
            $this->markFailed($charge, trim($status . ' ' . ($data['description'] ?? '')), $data);
        } else {
            $charge->save(); // still pending
        }

        return 'OK';
    }

    /**
     * Admin settles a pending GKash charge after checking the GKash portal.
     * "paid" keeps the current card token unless $newToken is given (GKash's
     * new epkey is only known from its reply or callback).
     */
    public function resolveGkashPending(SubscriptionRenewalCharge $charge, string $as, ?string $newToken = null): string
    {
        if (!in_array($charge->status, [SubscriptionRenewalCharge::PENDING, SubscriptionRenewalCharge::ACCEPTED], true)) {
            return "Charge {$charge->reference} is already {$charge->status}; nothing changed.";
        }
        if ($as === 'paid') {
            $this->markPaid($charge, ['resolved_by' => 'admin', 'at' => now()->toDateTimeString()], $newToken);
            return "Marked {$charge->reference} as PAID; the subscription now runs until " . Carbon::parse($charge->cycle_date)->addMonth()->toDateString() . '.';
        }
        $this->markFailed($charge, 'Admin: GKash portal shows this charge was not successful.');
        return "Marked {$charge->reference} as FAILED; the customer was notified and tomorrow's run will retry (if attempts remain).";
    }

    /** Current plan price (so an upgrade is billed at the new price), not the sign-up order's amount. */
    protected function currentPlanAmount(Subscription $subscription, $order): float
    {
        $setting = Setting::find(1);
        $amount = $setting ? $subscription->planPrice($setting) : 0;
        return $amount >= 1 ? (float) $amount : (float) $order->grand_total;
    }

    protected function notifyAdmin(string $subject, string $body): void
    {
        Log::warning($subject, ['details' => $body]);
        try {
            (new TelegramService())->notifyAdmin("⚠️ <b>" . e($subject) . "</b>\n" . e($body));
        } catch (\Throwable $e) {
            Log::error('Telegram admin alert failed', ['error' => $e->getMessage()]);
        }
        try {
            $email = \App\Models\User::superAdminEmail();
            if ($email) {
                (new OneSignalService())->sendEmail($email, 'Auto Maid admin: ' . $subject, nl2br(e($body)));
            }
        } catch (\Throwable $e) {
            Log::error('Admin email alert failed', ['error' => $e->getMessage()]);
        }
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

    protected function markPaid(SubscriptionRenewalCharge $charge, array $data, ?string $newToken = null): void
    {
        DB::transaction(function () use ($charge, $data, $newToken) {
            $recurring = $charge->paymentRecurring;
            $subscription = $charge->subscription;
            $payment = $recurring?->payment;
            $order = $payment?->order;

            $charge->status = SubscriptionRenewalCharge::PAID;
            $charge->result_at = now();
            $charge->save();

            if (!$recurring || !$subscription || !$order) {
                Log::error('Renewal paid but subscription records are missing', ['charge_id' => $charge->id]);
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
                    // GKash issues a new epkey with every successful charge;
                    // Fiuu's token stays the same.
                    'token' => $newToken ?: $recurring->token,
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

        Log::info('Subscription renewal paid', ['gateway' => $charge->gateway, 'reference' => $charge->reference, 'tran_id' => $charge->tran_id]);
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

        Log::warning('Subscription renewal failed', ['gateway' => $charge->gateway, 'reference' => $charge->reference, 'reason' => $reason, 'attempt' => $failed, 'final' => $finalAttempt]);

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
