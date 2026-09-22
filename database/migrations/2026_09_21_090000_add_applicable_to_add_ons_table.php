<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('add_ons', function (Blueprint $table) {
            if (!Schema::hasColumn('add_ons', 'applicable_to')) {
                // 'normal' | 'dry_cleaning' | 'both' — which booking
                // type(s) this add-on shows up for. Existing add-ons
                // default to 'both' so nothing already configured
                // silently disappears from either flow after this
                // migration runs.
                $table->string('applicable_to', 20)->default('both')->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('add_ons', function (Blueprint $table) {
            if (Schema::hasColumn('add_ons', 'applicable_to')) {
                $table->dropColumn('applicable_to');
            }
        });
    }
};
