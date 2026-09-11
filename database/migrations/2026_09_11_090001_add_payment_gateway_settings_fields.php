<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'payment_gateway_booking')) {
                $table->string('payment_gateway_booking', 20)->default('fiuu');
            }
            if (!Schema::hasColumn('settings', 'payment_gateway_dry_cleaning')) {
                $table->string('payment_gateway_dry_cleaning', 20)->default('fiuu');
            }
            if (!Schema::hasColumn('settings', 'payment_gateway_bag_purchase')) {
                $table->string('payment_gateway_bag_purchase', 20)->default('fiuu');
            }
            if (!Schema::hasColumn('settings', 'payment_gateway_subscription')) {
                // Locked to 'fiuu' in the admin form (see SettingResource)
                // until GKash recurring/pre-auth billing is actually
                // built — the monthly renewal job only knows how to
                // charge via Fiuu's recurring API today. Stored as its
                // own column now (rather than added later) so the
                // per-subscription lock-in (subscriptions.payment_gateway,
                // see the other migration in this pair) has a consistent
                // "what's currently active" value to read at signup from
                // day one.
                $table->string('payment_gateway_subscription', 20)->default('fiuu');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            foreach ([
                'payment_gateway_subscription',
                'payment_gateway_bag_purchase',
                'payment_gateway_dry_cleaning',
                'payment_gateway_booking',
            ] as $column) {
                if (Schema::hasColumn('settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
