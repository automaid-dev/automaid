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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();  
            $table->string('series_no')->nullable();

            $table->unsignedInteger('order_id')->index()->nullable();
            $table->unsignedInteger('user_id')->index()->nullable();
            $table->string('payment_method', 50)->nullable(); // web/recurring
            $table->string('payment_type', 50)->nullable(); // bag/subscription/booking
            $table->string('payment_status', 50)->nullable(); // Unpaid/Paid
            $table->text('desc')->nullable();

            $table->text('data')->nullable();
            $table->decimal('amount', 18, 2)->default(0)->nullable();
            $table->boolean('is_paid')->default(false);
            $table->string('file', 250)->nullable();
            $table->datetime('paid_at')->nullable();

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
        Schema::dropIfExists('payments');
    }
};
