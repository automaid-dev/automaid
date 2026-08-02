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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();  
            $table->unsignedInteger('user_id')->index()->nullable();

            $table->string('unit_no', 100)->nullable();                     
            $table->string('floor', 100)->nullable();                     
            $table->string('block', 100)->nullable();                     
            $table->string('address_line_1', 250)->nullable();
            $table->string('address_line_2', 250)->nullable();
            $table->string('address_line_3', 250)->nullable();
            $table->string('postcode', 100)->nullable();   
            $table->string('city', 100)->nullable();                     
            $table->unsignedInteger('state_id')->index()->nullable();
            $table->unsignedInteger('country_id')->index()->nullable();
            $table->string('address_title', 100)->nullable();                     
            $table->decimal('latitude', 18, 5)->nullable();
            $table->decimal('longitude', 18, 5)->nullable();
            
            $table->string('status', 50)->nullable();
            $table->unsignedInteger('updated_by')->index()->nullable();            
            $table->softDeletes();            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
