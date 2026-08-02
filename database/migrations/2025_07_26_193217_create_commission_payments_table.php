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
        Schema::create('commission_payments', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();  
            $table->unsignedInteger('commission_id')->index()->nullable();            
            $table->unsignedInteger('commission_transaction_id')->index()->nullable();            
            $table->boolean('is_paid')->default(0);
            $table->datetime('paid_at')->nullable();
            $table->unsignedInteger('paid_by')->index()->nullable();
            $table->decimal('amount', 8, 2)->nullable();
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
        Schema::dropIfExists('commission_payments');
    }
};
