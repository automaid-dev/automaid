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
        Schema::create('payment_recurrings', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug')->unique()->index();            
            $table->unsignedInteger('payment_id')->index()->nullable();
            $table->unsignedInteger('subscription_id')->index()->nullable();
            $table->string('token', 250)->nullable();            
            $table->date('payment_date')->nullable();
            $table->date('next_payment_date')->nullable();
            $table->text('data')->nullable();     
            $table->decimal('amount', 18, 2)->default(0)->nullable();
            $table->boolean('is_paid')->default(false);
            $table->datetime('paid_at')->nullable();       
            $table->string('status', 100)->nullable();
            $table->softDeletes();             
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_recurrings');
    }
};
