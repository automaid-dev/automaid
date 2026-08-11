<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('sst_percent', 5, 2)->nullable()->default(8)->after('discount_limit');
            $table->time('same_day_cutoff_time')->nullable()->default('12:00:00')->after('sst_percent');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['sst_percent', 'same_day_cutoff_time']);
        });
    }
};
