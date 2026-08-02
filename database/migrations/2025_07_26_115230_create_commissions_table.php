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
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();  
            $table->unsignedInteger('user_id')->index()->nullable();            

            $table->decimal('balance', 8, 2)->nullable();
            $table->string('status', 50)->nullable(); // active/suspended
            $table->datetime('last_transaction_at')->nullable();

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
        Schema::dropIfExists('commissions');
    }
};
