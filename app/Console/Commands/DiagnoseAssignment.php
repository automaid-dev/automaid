<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Diagnostic tool — checks a specific order against every condition the
 * auto-assignment command relies on, and reports exactly which ones pass
 * or fail. Built because rider/merchant matching depends on several
 * conditions at once (on-duty status, city coverage, coordinates,
 * account status), and guessing which one is blocking a specific order
 * wastes time better spent just checking directly.
 *
 * Usage: php artisan automaid:diagnose-assignment {order_id}
 */
class DiagnoseAssignment extends Command
{
    protected $signature = 'automaid:diagnose-assignment {order_id}';
    protected $description = 'Checks a specific order against every rider/merchant auto-assignment condition and reports exactly what passes or fails.';

    public function handle()
    {
        $orderId = $this->argument('order_id');
        $order = Order::find($orderId);

        if (!$order) {
            $this->error("Order #{$orderId} not found.");
            return 1;
        }

        $this->info("=== Order #{$order->id} ===");
        $this->line("order_type: {$order->order_type}");
        $this->line("is_pending_assign: " . ($order->is_pending_assign ? 'true' : 'false'));
        $this->line("billing_city: " . ($order->billing_city ?? '(empty — this alone would block every rider/merchant match)'));

        $booking = $order->booking;
        $this->line("pickup_date: " . ($booking?->pickup_date ?? '(no booking found)'));

        $this->newLine();
        $this->info('--- order_statuses (pending-acceptance rows) ---');
        $statuses = $order->order_statuses()->whereIn('code', ['11', '21'])->get();
        if ($statuses->isEmpty()) {
            $this->warn('No code 11/21 order_status rows found for this order at all — nothing for the assignment command to act on.');
        }
        foreach ($statuses as $s) {
            $this->line("code={$s->code} is_done=" . ($s->is_done ? 'true' : 'false') . " is_check_queue=" . ($s->is_check_queue ? 'true' : 'false'));
        }

        $this->newLine();
        $this->info('--- assign_jobs for this order ---');
        $jobs = $order->assign_jobs()->get();
        if ($jobs->isEmpty()) {
            $this->warn('No assign_jobs exist for this order at all.');
        }
        foreach ($jobs as $j) {
            $this->line("code={$j->code} user_id={$j->user_id} is_queue=" . ($j->is_queue ? 'true' : 'false') . " is_accepted=" . ($j->is_accepted ? 'true' : 'false'));
        }

        $this->newLine();
        $this->info('--- Riders currently on duty (is_duty=true) — checked against every matching condition ---');
        $riders = User::role('rider')->where('is_duty', true)->get();
        if ($riders->isEmpty()) {
            $this->warn('No riders are currently on duty at all — that alone fully explains no assignment.');
        }
        foreach ($riders as $user) {
            $this->line("Rider: {$user->name} ({$user->email})");
            $hasRiderProfile = $user->rider ? 'yes' : 'NO — missing rider profile record';
            $this->line("  has rider profile: {$hasRiderProfile}");
            $isActive = $user->status === User::ACTIVE ? 'yes' : "NO — status is '{$user->status}', needs to be '" . User::ACTIVE . "'";
            $this->line("  account active: {$isActive}");
            $hasCoords = ($user->latitude && $user->longitude) ? 'yes' : 'NO — latitude/longitude not set';
            $this->line("  has coordinates: {$hasCoords}");
            $coveredCities = $user->covered_locations()->where('is_active', true)->with('city')->get()->pluck('city.name')->filter()->implode(', ');
            $matchesCity = $order->billing_city && str_contains(strtolower($coveredCities), strtolower($order->billing_city))
                ? 'yes'
                : "NO — covers [{$coveredCities}], order needs '{$order->billing_city}'";
            $this->line("  covers order's city: {$matchesCity}");
            $this->newLine();
        }

        $this->newLine();
        $this->info('--- Merchants currently on duty (is_duty=true) ---');
        $merchants = User::role('merchant')->where('is_duty', true)->get();
        if ($merchants->isEmpty()) {
            $this->warn('No merchants are currently on duty at all — that alone fully explains no assignment.');
        }
        foreach ($merchants as $user) {
            $this->line("Merchant: {$user->name} ({$user->email})");
            $hasMerchantProfile = $user->merchant ? 'yes' : 'NO — missing merchant profile record';
            $this->line("  has merchant profile: {$hasMerchantProfile}");
            $isActive = $user->status === User::ACTIVE ? 'yes' : "NO — status is '{$user->status}'";
            $this->line("  account active: {$isActive}");
            $hasCoords = ($user->latitude && $user->longitude) ? 'yes' : 'NO — latitude/longitude not set';
            $this->line("  has coordinates: {$hasCoords}");
            $coveredCities = $user->covered_locations()->where('is_active', true)->with('city')->get()->pluck('city.name')->filter()->implode(', ');
            $matchesCity = $order->billing_city && str_contains(strtolower($coveredCities), strtolower($order->billing_city))
                ? 'yes'
                : "NO — covers [{$coveredCities}], order needs '{$order->billing_city}'";
            $this->line("  covers order's city: {$matchesCity}");
            $this->newLine();
        }

        return 0;
    }
}
