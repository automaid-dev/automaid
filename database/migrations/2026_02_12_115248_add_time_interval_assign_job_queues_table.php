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
            $table->unsignedInteger('time_interval')->nullable()->after('distance');
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
