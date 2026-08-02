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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();  
            $table->string('code', 250)->nullable();  
            $table->text('description')->nullable();  
            $table->decimal('discount_amount', 18, 5)->nullable();
            $table->datetime('start_at')->nullable();
            $table->datetime('expired_at')->nullable();
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
        Schema::dropIfExists('vouchers');
    }
};
