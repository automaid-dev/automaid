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
        Schema::create('credit_cards', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique(); 
            $table->unsignedInteger('subscription_id')->index()->nullable();
            $table->string('card_no', 100)->nullable();   
            $table->date('expired_date')->nullable();   
            $table->string('security_code', 100)->nullable();   
            $table->string('cardholder_name', 100)->nullable();   
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
        Schema::dropIfExists('credit_cards');
    }
};
