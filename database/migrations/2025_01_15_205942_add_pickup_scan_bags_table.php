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
            $table->datetime('scanned_at')->nullable()->after('manually_by');
            $table->datetime('pickup_scan_at')->nullable()->after('scanned_at');
            $table->datetime('pickup_manual_at')->nullable()->after('pickup_scan_at');
            $table->unsignedInteger('pickup_scan_by')->index()->nullable()->after('pickup_manual_at');
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
