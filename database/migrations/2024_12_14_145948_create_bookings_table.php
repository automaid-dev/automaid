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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();  
            $table->string('series_no')->nullable();
            $table->unsignedInteger('order_id')->index()->nullable();            
            $table->unsignedInteger('user_id')->index()->nullable();

            $table->unsignedInteger('pickup_location')->index()->nullable();
            $table->date('pickup_date')->nullable();
            $table->time('pickup_start_time')->nullable();
            $table->time('pickup_end_time')->nullable();
            $table->integer('pickup_bag_quantity')->nullable();
            $table->string('pickup_voucher', 100)->nullable();
            $table->boolean('is_folding')->default(0);

            $table->decimal('washing_charge', 8, 2)->nullable();
            $table->decimal('addon_charge', 8, 2)->nullable();
            $table->decimal('discount', 8, 2)->nullable();
            $table->decimal('delivery_charge', 8, 2)->nullable();
            $table->decimal('grand_total', 8, 2)->nullable();

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
        Schema::dropIfExists('bookings');
    }
};
