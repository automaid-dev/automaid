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
        Schema::table('bags', function (Blueprint $table) {
            $table->dropColumn(['scanned_by', 'manually_by', 'scanned_at', 'pickup_scan_at', 'pickup_manual_at', 'pickup_scan_by', 'created_by']);
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bags', function (Blueprint $table) {
            //
        });
    }
};
