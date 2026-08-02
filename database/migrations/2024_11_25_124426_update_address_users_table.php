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
        Schema::table('users', function (Blueprint $table) {
            $table->text('d_address_line_1')->nullable();
            $table->text('d_address_line_2')->nullable();
            $table->text('d_address_line_3')->nullable();
            $table->string('d_postcode', 100)->nullable();   
            $table->string('d_city', 100)->nullable();                     
            $table->unsignedInteger('d_state_id')->index()->nullable();
            $table->unsignedInteger('d_country_id')->index()->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
