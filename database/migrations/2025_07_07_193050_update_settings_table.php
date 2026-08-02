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
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('merchant_outlet_partner_commission', 18, 5)->default(0);
            $table->decimal('merchant_automaid_outlet_commission', 18, 5)->default(0);
            $table->decimal('merchant_minimum_commission', 18, 5)->default(0);

            $table->decimal('rider_gig_worker_commission', 18, 5)->default(0);
            $table->decimal('rider_staff_automaid_commission', 18, 5)->default(0);
            $table->decimal('rider_minimum_commission', 18, 5)->default(0);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            //
        });
    }
};
