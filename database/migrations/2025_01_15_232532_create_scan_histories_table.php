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
        Schema::create('scan_histories', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();  
            $table->string('qrcode', 250)->nullable();            
            $table->boolean('is_scan')->default(0);            
            $table->boolean('is_manual')->default(0);            
            $table->datetime('scan_at')->nullable();  
            $table->unsignedInteger('scan_by')->index()->nullable();           
            $table->timestamps();
            $table->softDeletes();  
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_histories');
    }
};
