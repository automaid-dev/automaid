<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // start_at/expired_at previously existed on this table but
            // were dropped by 2025_05_29_085449_remove_start_end_date_vouchers_table.php
            // — restored here since "period of usage" needs them again.
            if (!Schema::hasColumn('vouchers', 'start_at')) {
                $table->datetime('start_at')->nullable()->after('discount_amount');
            }
            if (!Schema::hasColumn('vouchers', 'expired_at')) {
                $table->datetime('expired_at')->nullable()->after('start_at');
            }

            // Minimum purchase requirement — exactly one of these
            // applies at a time, selected via minimum_requirement_type.
            if (!Schema::hasColumn('vouchers', 'minimum_requirement_type')) {
                // 'none' | 'amount' | 'items'
                $table->string('minimum_requirement_type', 20)->default('none')->after('expired_at');
            }
            if (!Schema::hasColumn('vouchers', 'minimum_purchase_amount')) {
                $table->decimal('minimum_purchase_amount', 18, 5)->nullable()->after('minimum_requirement_type');
            }
            if (!Schema::hasColumn('vouchers', 'minimum_total_items')) {
                $table->unsignedInteger('minimum_total_items')->nullable()->after('minimum_purchase_amount');
            }

            // Maximum usage limit — these three are independent
            // (admin can enable none, some, or all). usage_limit
            // (total redemptions across everyone) already existed;
            // these two are new. NULL on any of the three means that
            // particular cap is not enforced.
            if (!Schema::hasColumn('vouchers', 'usage_limit_per_customer')) {
                $table->unsignedInteger('usage_limit_per_customer')->nullable()->after('usage_limit');
            }
            if (!Schema::hasColumn('vouchers', 'max_discount_amount_cap')) {
                $table->decimal('max_discount_amount_cap', 18, 5)->nullable()->after('usage_limit_per_customer');
            }
        });

        Schema::table('voucher_users', function (Blueprint $table) {
            // Records the actual discount given for THIS specific
            // redemption — needed to enforce max_discount_amount_cap
            // accurately (summing this column, rather than depending
            // on orders.discount, which could in principle be edited
            // for unrelated reasons after the fact).
            if (!Schema::hasColumn('voucher_users', 'discount_amount')) {
                $table->decimal('discount_amount', 18, 5)->nullable()->after('order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            foreach ([
                'max_discount_amount_cap',
                'usage_limit_per_customer',
                'minimum_total_items',
                'minimum_purchase_amount',
                'minimum_requirement_type',
                'expired_at',
                'start_at',
            ] as $column) {
                if (Schema::hasColumn('vouchers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('voucher_users', function (Blueprint $table) {
            if (Schema::hasColumn('voucher_users', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
        });
    }
};
