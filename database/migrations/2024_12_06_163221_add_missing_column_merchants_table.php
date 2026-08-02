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
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('unit_no', 250)->nullable();
            $table->string('block', 250)->nullable();
            $table->text('address_line_1')->nullable();
            $table->text('address_line_2')->nullable();
            $table->text('address_line_3')->nullable();
            $table->string('postcode', 100)->nullable();   
            $table->string('city', 100)->nullable();                     
            $table->unsignedInteger('state_id')->index()->nullable();
            $table->unsignedInteger('country_id')->index()->nullable();

            $table->string('company_name', 250)->nullable();
            $table->string('ssm_no', 250)->nullable()->after('ssm_cert');
            $table->string('bank_name', 250)->nullable();
            $table->string('bank_no', 250)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            //
        });
    }
};
