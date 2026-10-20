<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\SubscriptionRenewalCharge;
use App\Services\SubscriptionRenewalService;
use Illuminate\Console\Command;

/**
 * php artisan automaid:rm-test-renewal 343                      dry run: what would be charged
 * php artisan automaid:rm-test-renewal 343 --status             list renewal attempts only
 * php artisan automaid:rm-test-renewal 343 --charge             really charge now (asks first)
 * php artisan automaid:rm-test-renewal 343 --resolve=SR343-20261108-1 --as=paid|failed
 *                                                                  settle a charge Revenue Monster left pending
 */
class TestRevenueMonsterRenewal extends Command
{
    protected $signature = 'automaid:rm-test-renewal {subscription_id}
        {--charge : Really charge the stored card now, even if the next payment date has not arrived}
        {--status : Only list renewal attempts}
        {--resolve= : Reference of a pending charge to settle}
        {--as= : paid or failed (with --resolve)}
        {--epkey= : (not used for Revenue Monster) (optional, with --resolve --as=paid)}';

    protected $description = 'Test or manage the Revenue Monster monthly renewal for one subscription (dry run unless --charge)';

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
            $this->table(['Reference', 'Cycle', 'Amount', 'Status', 'RM transaction', 'Reason', 'Created'], $attempts->map(fn ($c) => [
                $c->reference, $c->cycle_date?->toDateString(), $c->amount, $c->status, $c->tran_id, $c->reason, $c->created_at,
            ])->all());
        } else {
            $this->line('No renewal attempts yet.');
        }
        if ($this->option('status')) {
            return self::SUCCESS;
        }

        if ($subscription->payment_gateway !== 'revenuemonster') {
            $this->error('This subscription is not on Revenue Monster (use automaid:fiuu-test-renewal or automaid:gkash-test-renewal).');
            return self::FAILURE;
        }

        $service = new SubscriptionRenewalService();

        if ($reference = $this->option('resolve')) {
            $as = $this->option('as');
            if (!in_array($as, ['paid', 'failed'], true)) {
                $this->error('Use --as=paid or --as=failed.');
                return self::FAILURE;
            }
            $charge = $attempts->firstWhere('reference', $reference);
            if (!$charge) {
                $this->error("No charge {$reference} on this subscription.");
                return self::FAILURE;
            }
            if (!$this->confirm("Mark {$reference} (RM{$charge->amount}) as " . strtoupper($as) . '? Only do this after checking the Revenue Monster portal.')) {
                return self::SUCCESS;
            }
            $this->line($service->resolveGkashPending($charge, $as, $this->option('epkey') ?: null));
            return self::SUCCESS;
        }

        $recurring = $subscription->recurring_active;
        if (!$recurring) {
            $this->error('No active stored card token for this subscription (was the card bound at sign-up?).');
            return self::FAILURE;
        }
        $this->line('Card token ' . substr((string) $recurring->token, 0, 6) . '… | next payment date ' . $recurring->next_payment_date);

        $charge = (bool) $this->option('charge');
        if ($charge && !$this->confirm("This will REALLY charge the customer's card now and start the next cycle from {$recurring->next_payment_date}. Continue?")) {
            return self::SUCCESS;
        }

        $result = $service->chargeRevenueMonster($recurring, dryRun: !$charge);
        $this->line("[{$result['action']}] {$result['message']}");
        return self::SUCCESS;
    }
}
