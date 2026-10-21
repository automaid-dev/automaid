<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Plan downgrade the customer scheduled for their next renewal. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('subscriptions', 'scheduled_plan_code')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->string('scheduled_plan_code', 20)->nullable()->after('plan_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscriptions', 'scheduled_plan_code')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('scheduled_plan_code');
            });
        }
    }
};
