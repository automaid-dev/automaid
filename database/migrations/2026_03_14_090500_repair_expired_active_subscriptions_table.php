<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Repairs subscriptions whose `status` column still says 'active'
     * but whose `end_date` has already passed — these are invisible to
     * the customer app (User::subscribe() is date-scoped) despite
     * showing "active" in the admin panel, because the renewal cron
     * that's supposed to extend end_date/renew_at either never ran for
     * them or was blocked by the inverted date comparison fixed
     * alongside this migration (CheckNextPaymentSubscription.php).
     *
     * Extends end_date/renew_at by one month from today for anything
     * currently in this stuck state, so it's immediately visible again
     * in the customer app. This is a one-time repair for existing data
     * — it doesn't change what length future subscription cycles use
     * (that's a separate decision, not something this migration
     * assumes an answer to).
     */
    public function up(): void
    {
        $newEndDate = now()->addMonth()->toDateString();

        DB::table('subscriptions')
            ->where('status', 'active')
            ->whereDate('end_date', '<', now()->toDateString())
            ->update([
                'end_date' => $newEndDate,
                'renew_at' => $newEndDate,
            ]);
    }

    public function down(): void
    {
        //
    }
};
