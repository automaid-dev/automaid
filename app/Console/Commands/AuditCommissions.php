<?php

namespace App\Console\Commands;

use App\Models\Commission;
use App\Models\CommissionTransaction;
use App\Models\Merchant;
use App\Models\Rider;
use App\Models\Setting;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes what each CommissionTransaction's amount SHOULD have been
 * (this order's own commission) vs what's actually stored, using the
 * same formula as CommissionService::getTotalCommission(). Built to
 * assess the blast radius of the running-balance bug fixed in
 * DeliveryController::insertCommissionEwallet / CommissionService::
 * insertCommissionEwallet — every transaction after a user's very
 * first one recorded the balance BEFORE that order instead of that
 * order's own commission.
 *
 * DEFAULT MODE IS READ-ONLY. Nothing is written unless --apply is
 * passed, and even then, only rows this command can safely be
 * confident about get touched — see the skip conditions below.
 *
 * Recomputation uses TODAY's commission settings (rate/min/limit).
 * If those settings were ever changed, older transactions computed
 * under a different rate will show a "diff" that isn't actually a bug
 * — just a rate that's since moved. The SETTINGS CHANGED SINCE column
 * flags any transaction created before the most recent recorded
 * change to the settings row, using the audits table (Setting is
 * Auditable) — treat those rows as "needs manual judgement", not
 * "definitely wrong".
 *
 * Skipped from --apply automatically, always shown in the report:
 *   - status = paid (money already actually paid out — never touched
 *     automatically)
 *   - amount != final_amount (admin has used the ViewCommission "edit"
 *     action on this row at some point — see that action, which only
 *     ever writes final_amount, never amount; a mismatch is the only
 *     reliable signal available that a human already made a
 *     deliberate call here)
 *   - order/booking/user data missing (can't recompute at all)
 *
 * Usage:
 *   php artisan automaid:audit-commissions                  # every EARNED transaction
 *   php artisan automaid:audit-commissions --user_id=42      # one user
 *   php artisan automaid:audit-commissions --order_id=1334   # one order
 *   php artisan automaid:audit-commissions --apply            # after reviewing, write corrections
 */
class AuditCommissions extends Command
{
    protected $signature = 'automaid:audit-commissions {--user_id=} {--order_id=} {--apply}';
    protected $description = 'Reports (and optionally fixes) commission transactions whose stored amount does not match this order\'s own recomputed commission.';

    public function handle()
    {
        $commissionService = new CommissionService();

        $query = CommissionTransaction::with(['commission.user.rider', 'commission.user.merchant', 'order.booking'])
            ->where('type', CommissionTransaction::EARNED)
            ->orderBy('order_id');

        if ($userId = $this->option('user_id')) {
            $query->whereHas('commission', fn ($q) => $q->where('user_id', $userId));
        }
        if ($orderId = $this->option('order_id')) {
            $query->where('order_id', $orderId);
        }

        $transactions = $query->get();

        if ($transactions->isEmpty()) {
            $this->info('No matching commission transactions found.');
            return 0;
        }

        // Most recent time the settings row itself was changed, per the
        // audits table — used only to flag rows for manual judgement,
        // not to reconstruct historical rates.
        $settingsLastChangedAt = DB::table('audits')
            ->where('auditable_type', Setting::class)
            ->where('auditable_id', 1)
            ->orderByDesc('created_at')
            ->value('created_at');

        $rows = [];
        $toApply = [];
        $skippedPaid = 0;
        $skippedEdited = 0;
        $skippedMissingData = 0;

        foreach ($transactions as $txn) {
            $commission = $txn->commission;
            $user = $commission?->user;
            $order = $txn->order;
            $booking = $order?->booking;

            if (!$commission || !$user || !$order || !$booking) {
                $skippedMissingData++;
                $rows[] = [
                    $txn->order_id ?? '-',
                    '-',
                    number_format((float) $txn->final_amount, 2),
                    '-',
                    '-',
                    'SKIP — missing order/booking/user data',
                ];
                continue;
            }

            if ($user->rider) {
                $role = User::RIDER;
                $type = $user->rider->type_rider;
                $roleLabel = 'rider #' . $user->id;
            } elseif ($user->merchant) {
                $role = User::MERCHANT;
                $type = $user->merchant->type_merchant;
                $roleLabel = 'merchant #' . $user->id;
            } else {
                $skippedMissingData++;
                $rows[] = [$order->id, '-', number_format((float) $txn->final_amount, 2), '-', '-', 'SKIP — user has no rider/merchant profile'];
                continue;
            }

            // Same total-basis as DeliveryController::deliveryConfirm —
            // washing + delivery + addon, minus discount, excluding SST.
            $total = ($booking->washing_charge ?? 0)
                + ($booking->delivery_charge ?? 0)
                + ($booking->addon_charge ?? 0)
                - ($booking->discount ?? 0);

            $correct = $commissionService->getTotalCommission($role, $type, $total);
            $stored = (float) $txn->final_amount;
            $diff = round($correct - $stored, 2);

            $isPaid = $txn->status === CommissionTransaction::PAID;
            $isManuallyEdited = round((float) $txn->amount, 2) !== round((float) $txn->final_amount, 2);
            $settingsMayHaveChanged = $settingsLastChangedAt && $txn->created_at->lt($settingsLastChangedAt);

            $flags = [];
            if ($isPaid) $flags[] = 'PAID — never touched';
            if ($isManuallyEdited) $flags[] = 'manually edited — skipped';
            if ($settingsMayHaveChanged) $flags[] = 'settings changed since — verify';
            if (empty($flags) && abs($diff) > 0.01) $flags[] = 'will correct';
            if (empty($flags)) $flags[] = 'ok';

            $rows[] = [
                $order->id,
                $roleLabel,
                number_format($stored, 2),
                number_format($correct, 2),
                ($diff > 0 ? '+' : '') . number_format($diff, 2),
                implode('; ', $flags),
            ];

            if ($isPaid) {
                $skippedPaid++;
            } elseif ($isManuallyEdited) {
                $skippedEdited++;
            } elseif (abs($diff) > 0.01) {
                $toApply[] = ['txn' => $txn, 'correct' => $correct];
            }
        }

        $this->table(
            ['Order', 'User', 'Stored (RM)', 'Recomputed (RM)', 'Diff (RM)', 'Notes'],
            $rows
        );

        $this->newLine();
        $this->info(count($toApply) . ' transaction(s) would be corrected.');
        if ($skippedPaid > 0) $this->line("{$skippedPaid} skipped — already paid.");
        if ($skippedEdited > 0) $this->line("{$skippedEdited} skipped — previously manually edited (review these yourself in the admin panel).");
        if ($skippedMissingData > 0) $this->line("{$skippedMissingData} skipped — missing order/booking/user data.");
        if ($settingsLastChangedAt) {
            $this->line("Commission settings were last changed at {$settingsLastChangedAt} — any transaction created before that is flagged above for manual verification, since it may have been correctly computed under a different rate.");
        }

        if (!$this->option('apply')) {
            $this->newLine();
            $this->comment('Dry run only — nothing was written. Re-run with --apply to write the corrections listed above.');
            return 0;
        }

        if (empty($toApply)) {
            $this->info('Nothing to apply.');
            return 0;
        }

        if (!$this->confirm('This updates ' . count($toApply) . ' transaction(s) and recalculates the affected users\' commission balances — these are real payout figures. Continue?')) {
            $this->comment('Aborted — nothing was written.');
            return 0;
        }

        $affectedCommissionIds = [];
        foreach ($toApply as $item) {
            /** @var CommissionTransaction $txn */
            $txn = $item['txn'];
            $txn->amount = $item['correct'];
            $txn->final_amount = $item['correct'];
            $txn->save();
            $affectedCommissionIds[$txn->commission_id] = true;
        }

        foreach (array_keys($affectedCommissionIds) as $commissionId) {
            $commission = Commission::find($commissionId);
            if (!$commission) continue;
            $commission->balance = $commission->transactions()->sum('final_amount');
            $commission->last_transaction_at = now();
            $commission->save();
        }

        $this->info(count($toApply) . ' transaction(s) corrected and ' . count($affectedCommissionIds) . ' balance(s) recalculated.');
        return 0;
    }
}
