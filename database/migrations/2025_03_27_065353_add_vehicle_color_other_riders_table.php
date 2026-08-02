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
        Schema::table('riders', function (Blueprint $table) {
            $table->string('vehicle_color_other', 100)->nullable()->after('vehicle_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            //
        });
    }
};
