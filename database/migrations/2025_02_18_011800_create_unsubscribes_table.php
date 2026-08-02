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
        Schema::create('unsubscribes', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug')->unique()->index();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->bigInteger('subscription_id')->unsigned()->nullable();
            $table->bigInteger('order_id')->unsigned()->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('status', 50)->nullable();
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
        Schema::dropIfExists('unsubscribes');
    }
};
