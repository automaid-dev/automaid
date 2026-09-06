<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('commission_transactions', 'commission_settlement_id')) {
                $table->unsignedInteger('commission_settlement_id')->index()->nullable()->after('commission_id');
            }
        });

        // `commission_payments` is recorded as already migrated in
        // this database's `migrations` table, but the table itself
        // doesn't actually exist — it was presumably dropped at some
        // point after that original migration ran. Recreating it here
        // (matching database/migrations/2025_07_26_193217_create_commission_payments_table.php
        // exactly) makes this migration self-healing rather than
        // depending on that older migration ever being re-run, which
        // Laravel won't do on its own since it's already marked complete.
        if (!Schema::hasTable('commission_payments')) {
            Schema::create('commission_payments', function (Blueprint $table) {
                $table->id();
                $table->string('hashslug', 20)->unique();
                $table->unsignedInteger('commission_id')->index()->nullable();
                $table->unsignedInteger('commission_transaction_id')->index()->nullable();
                $table->boolean('is_paid')->default(0);
                $table->datetime('paid_at')->nullable();
                $table->unsignedInteger('paid_by')->index()->nullable();
                $table->decimal('amount', 8, 2)->nullable();
                $table->string('status', 50)->nullable();
                $table->unsignedInteger('created_by')->index()->nullable();
                $table->unsignedInteger('updated_by')->index()->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        Schema::table('commission_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('commission_payments', 'commission_settlement_id')) {
                $table->unsignedInteger('commission_settlement_id')->index()->nullable()->after('commission_transaction_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('commission_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('commission_transactions', 'commission_settlement_id')) {
                $table->dropColumn('commission_settlement_id');
            }
        });

        Schema::table('commission_payments', function (Blueprint $table) {
            if (Schema::hasColumn('commission_payments', 'commission_settlement_id')) {
                $table->dropColumn('commission_settlement_id');
            }
        });
    }
};
