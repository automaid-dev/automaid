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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 18)->unique();   
            $table->string('title', 250)->nullable();
            $table->string('slug', 250)->nullable();
            $table->string('image_url', 250)->nullable();
            $table->string('size', 100)->nullable();
            $table->string('ext', 100)->nullable();            
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
        Schema::dropIfExists('announcements');
    }
};
