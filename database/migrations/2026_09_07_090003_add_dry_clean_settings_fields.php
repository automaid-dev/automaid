<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'dry_clean_max_items_per_bag')) {
                $table->unsignedInteger('dry_clean_max_items_per_bag')->default(20);
            }
            if (!Schema::hasColumn('settings', 'subscription_bronze_dryclean_discount')) {
                $table->decimal('subscription_bronze_dryclean_discount', 5, 2)->default(5.00);
            }
            if (!Schema::hasColumn('settings', 'subscription_silver_dryclean_discount')) {
                $table->decimal('subscription_silver_dryclean_discount', 5, 2)->default(10.00);
            }
            if (!Schema::hasColumn('settings', 'subscription_platinum_dryclean_discount')) {
                $table->decimal('subscription_platinum_dryclean_discount', 5, 2)->default(15.00);
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            foreach ([
                'subscription_platinum_dryclean_discount',
                'subscription_silver_dryclean_discount',
                'subscription_bronze_dryclean_discount',
                'dry_clean_max_items_per_bag',
            ] as $column) {
                if (Schema::hasColumn('settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
