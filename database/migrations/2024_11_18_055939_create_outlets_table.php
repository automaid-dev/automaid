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
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();          
            $table->string('name', 250)->nullable();
            $table->string('slug', 250)->nullable();
            $table->text('address_line_1')->nullable();
            $table->text('address_line_2')->nullable();
            $table->text('address_line_3')->nullable();
            $table->string('postcode', 50)->nullable();   
            $table->string('city', 100)->nullable();                     
            $table->unsignedInteger('state_id')->index()->nullable();
            $table->unsignedInteger('country_id')->index()->nullable();
            $table->string('status', 100)->nullable();
            $table->timestamps();
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outlets');
    }
};
