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
        Schema::create('bag_scans', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();
            $table->unsignedInteger('bag_id')->index()->nullable();
            $table->unsignedInteger('order_id')->index()->nullable();
            $table->unsignedInteger('scan_by')->index()->nullable();                                
            $table->string('type', 50)->nullable(); // scan/manual                        
            $table->string('status', 50)->nullable(); 
            $table->softDeletes();            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bag_scans');
    }
};
