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
        Schema::create('insurance_users', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();      
            $table->unsignedInteger('user_id')->index()->nullable();
            $table->unsignedInteger('order_id')->index()->nullable();
            $table->decimal('fee', 8, 2)->nullable();
            $table->unsignedInteger('created_by')->index()->nullable();
            $table->unsignedInteger('updated_by')->index()->nullable();
            $table->unsignedInteger('deleted_by')->index()->nullable();
            $table->softDeletes();            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_users');
    }
};
