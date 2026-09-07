<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['bookings', 'order_bookings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'service_category_id')) {
                    $table->unsignedInteger('service_category_id')->index()->nullable()->after('grand_total');
                }
                if (!Schema::hasColumn($tableName, 'items')) {
                    // Snapshot of {service_item_id, name, quantity, unit_price, subtotal}
                    // at order time — deliberately a JSON snapshot rather than a
                    // normalized line-items table, since prices must stay frozen
                    // to what the customer was actually charged even if the
                    // catalog price changes later, and this app has no existing
                    // pattern of normalized order line items to extend anyway
                    // (order_addons is a single free-text string field, not a
                    // structured cart).
                    $table->json('items')->nullable()->after('service_category_id');
                }
                if (!Schema::hasColumn($tableName, 'dry_clean_discount_percent')) {
                    $table->decimal('dry_clean_discount_percent', 5, 2)->nullable()->after('items');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['bookings', 'order_bookings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['dry_clean_discount_percent', 'items', 'service_category_id'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
