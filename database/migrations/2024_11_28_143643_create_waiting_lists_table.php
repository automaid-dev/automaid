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
        Schema::create('waiting_lists', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 18)->unique();   
            $table->string('name', 250)->nullable();
            $table->string('email', 250)->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('city', 100)->nullable();            
            $table->string('status', 50)->nullable();
            $table->timestamps();
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waiting_lists');
    }
};
