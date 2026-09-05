<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `pickup_photo_path` and `pickup_note` (the customer's mandatory
     * handoff photo + note, captured at booking time so the rider knows
     * exactly where the bag was left) have been read and written
     * throughout the app — Booking/OrderBooking models,
     * BookingController::store, FiuuController's payment-webhook path,
     * and the admin's "Laundry Bag Pickup Info" section — since the
     * feature was built. No migration ever actually created these two
     * columns on either table, though: they only exist on production
     * because someone added them directly (untracked schema drift,
     * exactly the kind of gap flagged as a recurring risk elsewhere in
     * this app). Guarded with hasColumn() rather than a plain
     * Schema::table() add, specifically because production likely
     * already has these columns from that manual change — an
     * unguarded add would fail with "duplicate column" there, while a
     * fresh/staging environment that never had the manual change needs
     * the columns actually created.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'pickup_photo_path')) {
                $table->string('pickup_photo_path', 250)->nullable()->after('is_folding');
            }
            if (!Schema::hasColumn('bookings', 'pickup_note')) {
                $table->text('pickup_note')->nullable()->after('pickup_photo_path');
            }
        });

        Schema::table('order_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('order_bookings', 'pickup_photo_path')) {
                $table->string('pickup_photo_path', 250)->nullable()->after('is_folding');
            }
            if (!Schema::hasColumn('order_bookings', 'pickup_note')) {
                $table->text('pickup_note')->nullable()->after('pickup_photo_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'pickup_note')) {
                $table->dropColumn('pickup_note');
            }
            if (Schema::hasColumn('bookings', 'pickup_photo_path')) {
                $table->dropColumn('pickup_photo_path');
            }
        });

        Schema::table('order_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('order_bookings', 'pickup_note')) {
                $table->dropColumn('pickup_note');
            }
            if (Schema::hasColumn('order_bookings', 'pickup_photo_path')) {
                $table->dropColumn('pickup_photo_path');
            }
        });
    }
};
