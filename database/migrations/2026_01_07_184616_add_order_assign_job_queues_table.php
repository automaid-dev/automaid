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
        Schema::table('assign_job_queues', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->nullable()->index()->after('order_id');            
            $table->unsignedInteger('queue_position')->nullable()->after('status');            
            $table->string('distance', 250)->nullable()->after('queue_position');  
            $table->dropColumn(['assign_job_id']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assign_job_queues', function (Blueprint $table) {
            //
        });
    }
};
