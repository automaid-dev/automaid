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
        Schema::table('order_completes', function (Blueprint $table) {
            $table->unsignedInteger('rate_rider_star')->nullable()->after('image3');
            $table->text('rate_rider_comment')->nullable()->after('rate_rider_star');
            $table->unsignedInteger('rate_merchant_star')->nullable()->after('rate_rider_comment');
            $table->text('rate_merchant_comment')->nullable()->after('rate_merchant_star');              
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_completes', function (Blueprint $table) {
            //
        });
    }
};
