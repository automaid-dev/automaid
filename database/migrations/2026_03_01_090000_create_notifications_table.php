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
     * only if the user has a player_id registered). The in-app record
     * always gets created regardless of whether email/push succeed, so
     * the customer app's Notifications screen is never dependent on
     * either of those working.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
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
        Schema::dropIfExists('notifications');
    }
};
