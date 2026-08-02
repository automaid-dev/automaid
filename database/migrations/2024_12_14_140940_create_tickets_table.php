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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();  
            $table->unsignedInteger('user_id')->index()->nullable();
            $table->unsignedInteger('order_id')->index()->nullable();
            $table->string('issue_type', 250)->nullable();
            $table->text('issue')->nullable();
            $table->string('image', 250)->nullable();
            $table->string('status', 100)->nullable();
            $table->unsignedInteger('updated_by')->index()->nullable();
            $table->timestamps();
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
