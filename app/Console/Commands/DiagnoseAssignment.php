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

        // Used later for the exact-query replication section — this is
        // literally what AssignOrderToRiderAndMerchant passes into its
        // city-matching whereHas clause, unmodified (no trim/lowercase).
        $city_name = $order->billing_city;

        $booking = $order->booking;
        $this->line("pickup_date: " . ($booking?->pickup_date ?? '(no booking found)'));

        $this->newLine();
        $this->info('--- order_statuses (ALL codes, not just 11/21 — a missing follow-up status, e.g. code 12 after 11 is marked done, is itself often the actual bug) ---');
        $statuses = $order->order_statuses()->orderBy('code')->get();
        if ($statuses->isEmpty()) {
            $this->warn('No order_status rows found for this order at all.');
        }
        foreach ($statuses as $s) {
            $this->line("code={$s->code} is_done=" . ($s->is_done ? 'true' : 'false') . " is_check_queue=" . ($s->is_check_queue ? 'true' : 'false'));
        }

        $this->newLine();
        $this->info('--- assign_jobs for this order (with dashboard visibility check for rider/merchant codes) ---');
        $jobs = $order->assign_jobs()->with('user')->get();
        if ($jobs->isEmpty()) {
            $this->warn('No assign_jobs exist for this order at all.');
        }
        foreach ($jobs as $j) {
            $ownerName = $j->user ? "{$j->user->name} ({$j->user->email}, id={$j->user_id})" : "id={$j->user_id} — USER NOT FOUND";
            $this->line("code={$j->code} owner={$ownerName} is_queue=" . ($j->is_queue ? 'true' : 'false') . " is_accepted=" . ($j->is_accepted ? 'true' : 'false') . " accepted_by=" . ($j->accepted_by ?? 'null') . " deleted_at=" . ($j->deleted_at ?? 'null'));

            // Only the pending-acceptance rider/merchant codes (11, 21)
            // ever show up on the assignee's "pending job" dashboard —
            // once accepted, the job moves to whatever "current job"
            // screen exists for that role instead, so checking dashboard
            // visibility only makes sense for these two codes.
            if (in_array($j->code, ['11', '21']) && $j->user) {
                $bookingStatus = $booking?->status ?? '(no booking)';
                $bookingCancelled = $bookingStatus === \App\Models\Booking::CANCEL;
                $shouldShowPending = $j->code == '11'
                    ? ($j->is_queue && !$j->is_accepted)
                    : !$j->is_accepted;

                $pickupDate = $booking?->pickup_date;
                $todayDate = now()->toDateString();
                $tab = null;
                if ($pickupDate) {
                    $tab = $pickupDate <= $todayDate ? 'Today' : 'Incoming';
                }

                $verdict = $bookingCancelled
                    ? "NOT VISIBLE — booking status is '{$bookingStatus}' (cancelled bookings are excluded entirely)"
                    : (!$shouldShowPending
                        ? 'NOT VISIBLE — already accepted or not queued (moved past the pending dashboard)'
                        : ($tab ? "VISIBLE on the '{$tab}' tab" : 'NOT VISIBLE — booking has no pickup_date, matches neither tab'));

                $this->line("  → dashboard check for {$ownerName}: {$verdict}");
            }
        }

        $this->newLine();
        $this->info('--- Riders currently on duty (is_duty=true) — checked against every auto-assignment matching condition ---');
        $riders = User::role('rider')->where('is_duty', true)->get();
        if ($riders->isEmpty()) {
            $this->warn('No riders are currently on duty at all — that alone fully explains no auto-assignment.');
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
            $this->warn('No merchants are currently on duty at all — that alone fully explains no auto-assignment.');
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

        $this->newLine();
        $this->info("--- Merchant match: EXACT query used by the auto-assign command (city_name = '{$city_name}') ---");
        // Replicates AssignOrderToRiderAndMerchant's merchant-matching
        // query verbatim, since the looser PHP-level "covers order's
        // city" check above (case-insensitive substring match) can say
        // "yes" while the real production query — an exact DB `where`
        // on cities.name — says something different, e.g. because of
        // case/whitespace mismatch between the order's free-text
        // billing_city and the admin-curated cities.name value.
        $exactQuery = User::role('merchant')
            ->has('merchant')
            ->whereHas('covered_locations', function ($q) use ($city_name) {
                $q->where('is_active', true);
                $q->whereHas('city', function ($c) use ($city_name) {
                    $c->where('name', $city_name);
                });
            })
            ->where('is_duty', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->active();
        $this->line('SQL: ' . $exactQuery->toRawSql());
        $exactMatches = $exactQuery->get();
        if ($exactMatches->isEmpty()) {
            $this->warn("Exact query returns ZERO merchants — this is why the auto-assign command found nothing, regardless of what the looser check above says.");
        }
        foreach ($exactMatches as $m) {
            $this->line("  MATCHED: {$m->name} ({$m->email})");
        }

        $this->newLine();
        $this->info('--- Raw cities.name values for every city this order\'s eligible merchants cover (to spot case/whitespace mismatches) ---');
        $allMerchantCities = \App\Models\CityUser::whereHas('user', function ($q) {
                $q->role('merchant');
            })
            ->with('city')
            ->get()
            ->pluck('city.name')
            ->filter()
            ->unique();
        if ($allMerchantCities->isEmpty()) {
            $this->warn('No merchant has ANY covered_locations city configured at all.');
        }
        foreach ($allMerchantCities as $cn) {
            $match = strcasecmp(trim($cn), trim($city_name)) === 0 ? ' ← case-insensitive match to order' : '';
            $exact = $cn === $city_name ? ' ← EXACT match to order' : '';
            $this->line("  '{$cn}'{$exact}{$match}");
        }

        $this->newLine();
        $this->info("--- Rider match: EXACT query used by the auto-assign command (city_name = '{$city_name}') ---");
        // Same reasoning as the merchant exact-query section above —
        // replicated verbatim so a rider match failure shows exactly
        // why, instead of relying on the looser PHP-level check.
        $exactRiderQuery = User::role('rider')
            ->has('rider')
            ->whereHas('covered_locations', function ($q) use ($city_name) {
                $q->where('is_active', true);
                $q->whereHas('city', function ($c) use ($city_name) {
                    $c->where('name', $city_name);
                });
            })
            ->where('is_duty', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->active();
        $this->line('SQL: ' . $exactRiderQuery->toRawSql());
        $exactRiderMatches = $exactRiderQuery->get();
        if ($exactRiderMatches->isEmpty()) {
            $this->warn("Exact query returns ZERO riders — this is why the auto-assign command found nothing, regardless of what the looser check above says.");
        }
        foreach ($exactRiderMatches as $m) {
            $this->line("  MATCHED: {$m->name} ({$m->email})");
        }

        $this->newLine();
        $this->info('--- Raw notifications table check (ground truth, bypasses the app entirely) ---');
        // Checks the actual `notifications` table directly for the
        // rider/merchant owning each code=11/21 job on this order —
        // this tells us definitively whether the notification was ever
        // created at all, separate from whether the app displays it,
        // whether push succeeded, or whether the fix was even deployed
        // when the job was created (jobs created before a fix landed
        // won't retroactively get notified).
        $jobOwners = \App\Models\AssignJob::where('order_id', $order->id)
            ->whereIn('code', [\App\Models\OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE, \App\Models\OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE])
            ->with('user')
            ->get();
        if ($jobOwners->isEmpty()) {
            $this->warn('No rider/merchant job exists for this order at all — nothing to check notifications for.');
        }
        foreach ($jobOwners as $job) {
            if (!$job->user) {
                $this->line("  code={$job->code}: owning user not found (user_id={$job->user_id})");
                continue;
            }
            $recentNotifs = $job->user->notifications()
                ->where('created_at', '>=', $order->created_at)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();
            $this->line("  {$job->user->name} ({$job->user->email}) — {$recentNotifs->count()} notification(s) since this order was created:");
            foreach ($recentNotifs as $n) {
                $data = $n->data;
                $title = $data['title'] ?? '(no title key)';
                $message = $data['message'] ?? '(no message key)';
                $this->line("    [{$n->created_at}] type={$n->type} title=\"{$title}\" message=\"{$message}\"");
            }
        }

        return 0;
    }
}
