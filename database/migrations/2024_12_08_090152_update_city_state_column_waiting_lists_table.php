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
        Schema::table('waiting_lists', function (Blueprint $table) {
            $table->unsignedInteger('city_id')->index()->nullable()->change();
            $table->unsignedInteger('state_id')->index()->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('waiting_lists', function (Blueprint $table) {
            //
        });
    }
};
