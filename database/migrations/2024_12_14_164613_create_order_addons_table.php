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
        Schema::create('order_addons', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();  
            
            $table->unsignedInteger('order_id')->index()->nullable();
            $table->unsignedInteger('user_id')->index()->nullable();
            $table->string('add_on', 250)->nullable();
            $table->string('status', 50)->nullable();

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
        Schema::dropIfExists('order_addons');
    }
};
