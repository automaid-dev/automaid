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
        Schema::table('bags', function (Blueprint $table) {
            $table->dropColumn(['series_no']);
            $table->unsignedInteger('user_id')->index()->nullable()->after('hashslug');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bags', function (Blueprint $table) {
            //
        });
    }
};
