<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * In-app notification history — every entry created here also
     * triggers an email (via OneSignalService::sendEmail) and attempts a
     * push notification (via OneSignalService::sendOneSignalNotification,
     * only if the user has a device_id registered). The in-app record
     * always gets created regardless of whether email/push succeed, so
     * the customer app's Notifications screen is never dependent on
     * either of those working.
     *
     * IMPORTANT: this table is deliberately named `customer_notifications`,
     * NOT `notifications` — that name is reserved by Laravel's own
     * built-in notification system (already used throughout this app,
     * and required by Filament's admin panel). An earlier version of
     * this migration used `notifications` and caused a real production
     * outage as a result — see the
     * 2026_03_10_080000_fix_notifications_table_conflict migration,
     * which repairs a database that already ran the broken version.
     * This corrected version is only relevant for a fresh install that
     * has never run the broken one.
     */
    public function up(): void
    {
        Schema::create('customer_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();
            $table->unsignedInteger('user_id');
            $table->string('type', 50); // account_created, bag_purchased, subscription_created, subscription_cancelled, new_booking
            $table->string('title');
            $table->text('body');
            $table->unsignedInteger('order_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notifications');
    }
};
