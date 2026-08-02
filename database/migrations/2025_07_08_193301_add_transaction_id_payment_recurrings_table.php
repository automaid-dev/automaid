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
        Schema::table('payment_recurrings', function (Blueprint $table) {
            $table->unsignedInteger('transaction_id')->index()->nullable()->after('subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_recurrings', function (Blueprint $table) {
            //
        });
    }
};
