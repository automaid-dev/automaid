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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique(); 
            $table->unsignedInteger('order_id')->index()->nullable();
            $table->unsignedInteger('payment_id')->index()->nullable();
            $table->date('date')->nullable();
            $table->string('type', 100)->nullable();   
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('status', 50)->nullable();
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
        Schema::dropIfExists('transactions');
    }
};
