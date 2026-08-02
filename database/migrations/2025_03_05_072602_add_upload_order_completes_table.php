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
        Schema::table('order_completes', function (Blueprint $table) {
            $table->dropColumn(['bag_id']);            
            $table->string('image1', 250)->nullable()->after('status');
            $table->string('image2', 250)->nullable()->after('image1');
            $table->string('image3', 250)->nullable()->after('image2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_completes', function (Blueprint $table) {
            //
        });
    }
};
