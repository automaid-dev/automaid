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
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();   
            $table->string('type_merchant', 250)->nullable();

            $table->unsignedInteger('washer_quantity')->nullable();
            $table->unsignedInteger('dryer_quantity')->nullable();

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
        Schema::dropIfExists('merchants');
    }
};
