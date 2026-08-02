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
        Schema::create('riders', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();   
            $table->string('type_rider', 250)->nullable();
            $table->string('type_vehicle', 250)->nullable();

            $table->string('emergency_name', 100)->nullable();
            $table->string('emergency_phone', 50)->nullable();
            $table->string('emergency_relation',100)->nullable();

            $table->string('plate_no', 250)->nullable();
            $table->string('vehicle_make', 250)->nullable();
            $table->text('vehicle_model')->nullable();

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
        Schema::dropIfExists('riders');
    }
};
