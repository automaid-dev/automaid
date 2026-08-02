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
        Schema::table('waiting_lists', function (Blueprint $table) {
            $table->renameColumn('phone', 'mobile_no');
            $table->string('postcode', 100)->nullable()->after('mobile_no');
            $table->renameColumn('city', 'city_id');
            $table->renameColumn('state', 'state_id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('waiting_lists', function (Blueprint $table) {
            //
        });
    }
};
