<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * URGENT FIX: an earlier patch created a `notifications` table for
     * a new in-app customer feature — but `notifications` is Laravel's
     * own reserved table name for its built-in notification system
     * (Illuminate\Notifications\Notifiable, used throughout this app via
     * $user->notify(...) for rider/merchant job pings, delivery updates,
     * support ticket alerts), AND Filament's admin panel uses the exact
     * same table for its own notification bell in the admin layout —
     * which is why this broke the whole admin panel, not just the new
     * customer feature.
     *
     * This renames things back to where they belong:
     *   - current `notifications` (the new custom-schema table, 1 real
     *     row of customer notification data) -> `customer_notifications`
     *   - `_old_notifications` (the real Laravel/Filament notifications
     *     table, with real historical data) -> back to `notifications`
     *
     * Order matters: free up the `notifications` name first, then
     * restore the real one into it.
     */
    public function up(): void
    {
        if (Schema::hasTable('notifications') && !Schema::hasTable('customer_notifications')) {
            Schema::rename('notifications', 'customer_notifications');
        }

        if (Schema::hasTable('_old_notifications') && !Schema::hasTable('notifications')) {
            Schema::rename('_old_notifications', 'notifications');
        }
    }

    /**
     * Reverse the migrations.
     *
     * Deliberately a no-op — reversing this would recreate the exact
     * conflict this migration exists to fix.
     */
    public function down(): void
    {
        //
    }
};
