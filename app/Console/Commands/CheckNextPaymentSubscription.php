<?php

namespace App\Console\Commands;

use App\Models\PaymentRecurring;
use App\Models\Subscription;
use App\Services\SubscriptionRenewalService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckNextPaymentSubscription extends Command
{
    protected $signature = 'automaid:check-next-payment-subscription {--dry-run : Show what would be charged without charging}';

    protected $description = 'Charge subscriptions whose next payment date has arrived (GKash, Fiuu and Revenue Monster)';

    /**
     * Picks up every active subscription whose next payment date is today
     * or already past (so a missed night or a failed attempt is retried),
     * and hands it to SubscriptionRenewalService, which:
     *  - bills the CURRENT plan price (upgrades included),
     *  - uses a unique reference per attempt (SR{sub}-{cycle}-{attempt}),
     *  - never charges a cycle twice (paid, or still pending at the gateway),
     *  - notifies the customer of failures and gives up after 3 attempts,
     *  - keeps the billing date anchored to the original cycle date.
     */
    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');

        $recurrings = PaymentRecurring::whereHas('subscription', function ($query) {
            $query->where(['status' => Subscription::ACTIVE]);
        })
            ->whereDate('next_payment_date', '<=', Carbon::today())
            ->whereNotNull('paid_at')
            ->where([
                'is_paid' => true,
                'status_payment' => PaymentRecurring::PAID,
            ])
            ->whereIn('status', [PaymentRecurring::SUBSCRIPTION, PaymentRecurring::SUBSCRIPTION_RENEWAL])
            ->get();

        if ($recurrings->isEmpty()) {
            $this->line('No subscriptions due.');
            return self::SUCCESS;
        }

        $service = new SubscriptionRenewalService();

        foreach ($recurrings as $recurring) {
            $subscription = $recurring->subscription;
            $gateway = in_array($subscription->payment_gateway, ['gkash', 'revenuemonster'], true) ? $subscription->payment_gateway : 'fiuu';

            try {
                $result = match ($gateway) {
                    'gkash' => $service->chargeGkash($recurring, $dryRun),
                    'revenuemonster' => $service->chargeRevenueMonster($recurring, $dryRun),
                    default => $service->chargeFiuu($recurring, $dryRun),
                };
                $this->line("Subscription #{$subscription->id} [{$gateway}] [{$result['action']}] {$result['message']}");
            } catch (\Throwable $e) {
                Log::error('Subscription renewal crashed', ['subscription_id' => $subscription->id, 'gateway' => $gateway, 'error' => $e->getMessage()]);
                $this->error("Subscription #{$subscription->id} [{$gateway}]: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
