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
        Schema::create('ewallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();  
            $table->unsignedInteger('ewallet_id')->index()->nullable();            
            $table->unsignedInteger('order_id')->index()->nullable();            
            $table->string('type', 50)->nullable(); // earned/transferred

            $table->decimal('amount', 8, 2)->nullable();
            $table->decimal('balance_before', 8, 2)->nullable();
            $table->decimal('balance_after', 8, 2)->nullable();
            $table->string('status', 50)->nullable();
            $table->text('desc')->nullable();

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
        Schema::dropIfExists('ewallet_transactions');
    }
};
