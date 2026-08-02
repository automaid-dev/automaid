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
        Schema::table('riders', function (Blueprint $table) {
            $table->string('ic_front', 250)->nullable();   
            $table->string('ic_back', 250)->nullable();   
            $table->string('license_front', 250)->nullable();   
            $table->string('license_back', 250)->nullable();   
            $table->string('jpj_grant', 250)->nullable();   

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            //
        });
    }
};
