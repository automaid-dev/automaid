<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tiered delivery pricing: the existing `delivery_price` setting
     * stays as the 1st bag's delivery charge (unchanged). These two new
     * fields control the rate for every additional bag in the same
     * order (2nd bag onward) — either a flat RM amount per additional
     * bag, or a percentage of the 1st bag's delivery price.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('delivery_additional_bag_type', 20)->nullable()->default('flat')->after('delivery_price'); // 'flat' or 'percent'
            $table->decimal('delivery_additional_bag_value', 18, 5)->nullable()->after('delivery_additional_bag_type'); // RM amount if type=flat, percentage number (e.g. 50 = 50%) if type=percent
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['delivery_additional_bag_type', 'delivery_additional_bag_value']);
        });
    }
};
