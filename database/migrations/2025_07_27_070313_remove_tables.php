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
        Schema::dropIfExists('commission_payments');
        Schema::dropIfExists('ewallet_transactions');
        Schema::dropIfExists('ewallets');
        // Add more tables as needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commission_payments', function (Blueprint $table) {
            //
        });
    }
};
