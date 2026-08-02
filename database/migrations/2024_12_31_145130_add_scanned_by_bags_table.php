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
            $table->unsignedInteger('scanned_by')->index()->nullable()->after('status');
            $table->unsignedInteger('manually_by')->index()->nullable()->after('scanned_by');
            
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
