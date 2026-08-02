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
        Schema::create('assign_job_queues', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();      
            $table->unsignedInteger('assign_job_id')->index()->nullable();
            $table->unsignedInteger('order_id')->index()->nullable();
            $table->string('status')->nullable()->comment('pending/queued');
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
        Schema::dropIfExists('assign_job_queues');
    }
};
