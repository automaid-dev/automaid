<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // This is a MANUAL refund record, not a real-time gateway
            // integration — ticking "refund" in the admin's Cancel
            // Order modal marks these fields and notifies the
            // customer, but does NOT itself move any money. The actual
            // refund still has to be processed separately (Fiuu's own
            // merchant dashboard, bank transfer, e-wallet, etc.) —
            // this exists so that fact is recorded and visible
            // alongside the order/payment, not so admin can skip doing
            // it. See the comment on the Cancel Order action for the
            // reasoning behind not calling a gateway refund API here.
            $table->boolean('is_refunded')->default(false)->after('paid_at');
            $table->decimal('refund_amount', 18, 2)->nullable()->after('is_refunded');
            $table->datetime('refunded_at')->nullable()->after('refund_amount');
            $table->text('refund_reason')->nullable()->after('refunded_at');
            $table->unsignedInteger('refunded_by')->index()->nullable()->after('refund_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['is_refunded', 'refund_amount', 'refunded_at', 'refund_reason', 'refunded_by']);
        });
    }
};
