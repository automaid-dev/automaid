<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per monthly charge attempt on a stored card token.
 *
 * Fiuu's recurring API only *accepts* a charge request; the real result
 * arrives later on the callback URL. This table is what ties that
 * callback (matched on `reference`, the unique OrderID we sent) back to
 * the subscription, and what stops the nightly job from charging twice.
 *
 * Also records which Fiuu merchant ID created each stored token
 * (payment_recurrings.merchant_id) — a token can only be charged by the
 * account that created it. Existing rows stay NULL = the normal account.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subscription_renewal_charges')) {
            Schema::create('subscription_renewal_charges', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('subscription_id')->index();
                $table->unsignedInteger('payment_recurring_id')->index()->nullable();
                $table->unsignedInteger('order_id')->index()->nullable(); // original subscription order
                $table->string('gateway', 20)->default('fiuu');
                $table->string('merchant_id', 100)->nullable();
                $table->string('reference', 32)->unique(); // OrderID sent to Fiuu, e.g. SR123-20261102-1
                $table->date('cycle_date')->index();        // the next_payment_date this charge is for
                $table->unsignedTinyInteger('attempt')->default(1);
                $table->string('plan_code', 20)->nullable();
                $table->decimal('amount', 18, 2);
                // pending -> accepted (waiting for callback) -> paid | failed
                $table->string('status', 20)->default('pending')->index();
                $table->string('tran_id', 50)->nullable();
                $table->string('reason', 300)->nullable();
                $table->text('request_response')->nullable();
                $table->text('callback_data')->nullable();
                $table->timestamp('result_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('payment_recurrings', 'merchant_id')) {
            Schema::table('payment_recurrings', function (Blueprint $table) {
                $table->string('merchant_id', 100)->nullable()->after('token');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_renewal_charges');
        if (Schema::hasColumn('payment_recurrings', 'merchant_id')) {
            Schema::table('payment_recurrings', function (Blueprint $table) {
                $table->dropColumn('merchant_id');
            });
        }
    }
};
