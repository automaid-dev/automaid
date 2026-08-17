<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Diagnostic tool — shows exactly what's going on with a customer's
 * subscription quota tracking: every subscription row they have (not
 * just the one the date-scoped relation currently resolves to), which
 * one (if any) is "active" right now, and their recent orders' quota
 * flags. Built because reasoning about this further without seeing the
 * actual data risked another wrong guess.
 *
 * Usage: php artisan automaid:diagnose-subscription {user_id}
 */
class DiagnoseSubscription extends Command
{
    protected $signature = 'automaid:diagnose-subscription {user_id}';
    protected $description = 'Shows every subscription row for a user, which one is currently active, and recent orders\' quota-usage flags.';

    public function handle()
    {
        $userId = $this->argument('user_id');
        $user = User::find($userId);

        if (!$user) {
            $this->error("User #{$userId} not found.");
            return 1;
        }

        $this->info("=== User #{$user->id} — {$user->name} ({$user->email}) ===");

        $this->newLine();
        $this->info('--- What User::subscribe() resolves to RIGHT NOW (date-scoped, status=active) ---');
        $resolved = $user->subscribe;
        if ($resolved) {
            $this->line("Subscription #{$resolved->id}: plan_code={$resolved->plan_code} orders_used_current_cycle={$resolved->orders_used_current_cycle} start_date={$resolved->start_date} end_date={$resolved->end_date}");
        } else {
            $this->warn('NULL — no subscription currently matches the active/date-window scope. This alone would explain the quota never incrementing (schedule() checks this exact relation).');
        }

        $this->newLine();
        $this->info('--- ALL subscription rows for this user (any status/date) ---');
        $all = Subscription::where('user_id', $user->id)->orderByDesc('id')->get();
        if ($all->isEmpty()) {
            $this->warn('No subscription rows exist for this user at all.');
        }
        foreach ($all as $s) {
            $marker = $resolved && $s->id === $resolved->id ? '  <-- this is the one User::subscribe() resolves to' : '';
            $this->line("#{$s->id}: status={$s->status} plan_code={$s->plan_code} orders_used_current_cycle={$s->orders_used_current_cycle} start_date={$s->start_date} end_date={$s->end_date}{$marker}");
        }

        $this->newLine();
        $this->info('--- Recent orders for this user (booking type only, last 10) ---');
        $orders = Order::where('user_id', $user->id)
            ->where('order_type', Order::BOOKING)
            ->orderByDesc('id')
            ->limit(10)
            ->get();
        if ($orders->isEmpty()) {
            $this->warn('No booking-type orders found for this user.');
        }
        foreach ($orders as $o) {
            $flag = property_exists($o, 'used_subscription_quota') || isset($o->used_subscription_quota)
                ? ($o->used_subscription_quota ? 'true' : 'false')
                : 'COLUMN MISSING — migration not run?';
            $this->line("Order #{$o->id}: status={$o->status} used_subscription_quota={$flag} created_at={$o->created_at}");
        }

        return 0;
    }
}
