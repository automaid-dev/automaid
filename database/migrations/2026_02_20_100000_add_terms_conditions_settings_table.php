<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores the path to the admin-uploaded Terms & Conditions PDF (via
     * Settings > General). The app reads this back as a full URL through
     * Setting::getTermsConditionsUrlAttribute() — same pattern as
     * User::avatar_url — rather than exposing the raw storage path.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('terms_conditions')->nullable()->after('bag_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('terms_conditions');
        });
    }
};
