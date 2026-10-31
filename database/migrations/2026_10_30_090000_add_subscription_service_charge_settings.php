<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separate payment gateway service charge for subscription payments.
 * The existing {gateway}_service_charge_percent columns now apply to
 * bookings, dry cleaning and bag purchases; these new columns apply to
 * subscription sign-ups, card updates, upgrades and monthly renewals.
 * Each new column starts with the gateway's current value, so nothing
 * changes until admin edits it.
 */
return new class extends Migration
{
    private const GATEWAYS = ['fiuu', 'gkash', 'revenuemonster'];

    public function up(): void
    {
        foreach (self::GATEWAYS as $gw) {
            $new = "{$gw}_subscription_service_charge_percent";
            if (Schema::hasColumn('settings', $new)) {
                continue;
            }
            Schema::table('settings', function (Blueprint $table) use ($new) {
                $table->decimal($new, 5, 2)->default(0);
            });
            $old = "{$gw}_service_charge_percent";
            if (Schema::hasColumn('settings', $old)) {
                DB::table('settings')->update([$new => DB::raw($old)]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::GATEWAYS as $gw) {
            $col = "{$gw}_subscription_service_charge_percent";
            if (Schema::hasColumn('settings', $col)) {
                Schema::table('settings', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }
};
