<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'payment_gateway')) {
                // 'fiuu' | 'gkash' — nullable since historical orders
                // predate this column and were all Fiuu; only new
                // orders going forward get this set explicitly.
                $table->string('payment_gateway', 20)->nullable()->after('order_type');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'payment_gateway')) {
                // Set once at signup and never changed afterwards
                // (until cancel + resubscribe) — this is what lets an
                // existing Fiuu subscriber keep recurring on Fiuu even
                // after admin switches the active gateway setting to
                // GKash for new signups. The monthly renewal job reads
                // THIS column, never the live admin setting.
                $table->string('payment_gateway', 20)->nullable()->after('plan_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'payment_gateway')) {
                $table->dropColumn('payment_gateway');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'payment_gateway')) {
                $table->dropColumn('payment_gateway');
            }
        });
    }
};
