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
        Schema::table('qrcode_users', function (Blueprint $table) {
            $table->unsignedInteger('order_id')->nullable()->after('hashslug');
            $table->unsignedInteger('deleted_by')->index()->nullable()->after('deleted_at');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qrcode_users', function (Blueprint $table) {
            //
        });
    }
};
