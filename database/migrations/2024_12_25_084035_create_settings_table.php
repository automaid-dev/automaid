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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();  
                   
            $table->decimal('rider_commission', 18, 5)->nullable();
            $table->decimal('merchant_commission', 18, 5)->nullable();
            $table->decimal('wash_fee', 18, 5)->nullable();
            $table->decimal('bag_price', 18, 5)->nullable();
            
            $table->unsignedInteger('created_by')->index()->nullable();            
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
        Schema::dropIfExists('settings');
    }
};
