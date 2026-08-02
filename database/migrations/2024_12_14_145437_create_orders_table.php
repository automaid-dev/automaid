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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();  
            $table->string('series_no', 50)->nullable();            
            $table->unsignedInteger('user_id')->index()->nullable();
            $table->string('status', 50)->nullable();

            $table->string('billing_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_phone')->nullable();

            $table->string('billing_address_line_1')->nullable();
            $table->string('billing_address_line_2')->nullable();
            $table->string('billing_country')->nullable();
            $table->string('billing_postcode')->nullable();
            $table->string('billing_state')->nullable();
            $table->string('billing_city')->nullable();

            $table->string('delivery_address_line_1')->nullable();
            $table->string('delivery_address_line_2')->nullable();
            $table->string('delivery_country')->nullable();
            $table->string('delivery_postcode')->nullable();
            $table->string('delivery_state')->nullable();
            $table->string('delivery_city')->nullable();

            $table->decimal('discount', 18, 2)->nullable();
            $table->decimal('sub_total', 18, 2)->nullable();
            $table->decimal('tax_total', 18, 2)->nullable();
            $table->decimal('grand_total', 18, 2)->nullable();

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
        Schema::dropIfExists('orders');
    }
};
