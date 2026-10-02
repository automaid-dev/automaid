<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\SubscriptionRenewalCharge;
use App\Services\SubscriptionRenewalService;
use Illuminate\Console\Command;

/**
 * Run the monthly Fiuu renewal for ONE subscription right now, without
 * waiting for its billing date. Dry run by default; --charge really
 * charges the stored card. The result arrives on Fiuu's callback a few
 * seconds later — re-run with --status to see it.
 *
 *   php artisan automaid:fiuu-test-renewal 326            # dry run
 *   php artisan automaid:fiuu-test-renewal 326 --charge   # real charge
 *   php artisan automaid:fiuu-test-renewal 326 --status   # show attempts
 */
class TestFiuuRenewal extends Command
{
    protected $signature = 'automaid:fiuu-test-renewal {subscription_id} {--charge : Really charge the stored card} {--status : Only list renewal attempts}';
    protected $description = 'Test the Fiuu monthly renewal for one subscription (dry run unless --charge)';

    public function handle(): int
    {
        $subscription = Subscription::find($this->argument('subscription_id'));
        if (!$subscription) {
            $this->error('Subscription not found.');
            return self::FAILURE;
        }

        $this->info("Subscription #{$subscription->id} | user #{$subscription->user_id} | plan {$subscription->plan_code} | status {$subscription->status} | gateway " . ($subscription->payment_gateway ?? 'fiuu') . " | ends " . ($subscription->end_date ?? '-'));

        $attempts = SubscriptionRenewalCharge::where('subscription_id', $subscription->id)->orderBy('id')->get();
        if ($attempts->isNotEmpty()) {
            $this->table(['Reference', 'Cycle', 'Amount', 'Status', 'Fiuu tranID', 'Reason', 'Created'], $attempts->map(fn ($c) => [
                $c->reference, $c->cycle_date?->toDateString(), $c->amount, $c->status, $c->tran_id, $c->reason, $c->created_at,
            ])->all());
        }
        if ($this->option('status')) {
            return self::SUCCESS;
        }

        if ($subscription->payment_gateway === 'gkash') {
            $this->error('This subscription is on GKash; this command only tests Fiuu.');
            return self::FAILURE;
        }

        $recurring = $subscription->recurring_active;
        if (!$recurring) {
            $this->error('No active stored card token for this subscription (did Fiuu return a token in extraP at sign-up?).');
            return self::FAILURE;
        }
        $this->line('Token account: ' . ($recurring->merchant_id ?: '(normal Fiuu account — signed up before the recurring account)') . ' | next payment date ' . $recurring->next_payment_date);

        $charge = (bool) $this->option('charge');
        if ($charge && !$this->confirm('This will REALLY charge the customer\'s card now. Continue?')) {
            return self::SUCCESS;
        }

        $result = (new SubscriptionRenewalService())->chargeFiuu($recurring, dryRun: !$charge);
        $this->line("[{$result['action']}] {$result['message']}");
        if ($result['action'] === 'accepted') {
            $this->line('Fiuu will POST the result to the callback URL shortly. Check with: php artisan automaid:fiuu-test-renewal ' . $subscription->id . ' --status');
        }
        return self::SUCCESS;
    }
}
