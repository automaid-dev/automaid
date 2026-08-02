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
            $table->unsignedInteger('discount_percent')->nullable();         
            $table->unsignedInteger('discount_limit')->nullable();

            $table->unsignedInteger('birthday_reward_amount')->nullable();
            $table->unsignedInteger('birthday_reward_min')->nullable();
            $table->unsignedInteger('insurance_fee')->nullable();
            $table->unsignedInteger('insurance_coverage')->nullable();            
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
