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
        Schema::table('banners', function (Blueprint $table) {
            // Dashboard banners only ever needed a short caption
            // (title). The new onboarding carousel slides need a
            // fuller supporting sentence under the title too (see the
            // reference marketing images), so this is added here
            // rather than building a separate table — same underlying
            // concept (admin-managed image + copy), just a richer
            // placement.
            $table->text('description')->nullable()->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
