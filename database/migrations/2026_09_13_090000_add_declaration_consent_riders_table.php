<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            if (!Schema::hasColumn('riders', 'declaration_accepted')) {
                // True only once every checkbox on the Declarations
                // (Pengisytiharan) screen was checked — the app sends
                // this as a single combined flag, not 5 separate
                // fields, matching "all checkbox checked as single yes
                // in field".
                $table->boolean('declaration_accepted')->default(false)->after('status');
            }
            if (!Schema::hasColumn('riders', 'consent_accepted')) {
                // Same idea for the Consents (Persetujuan) screen's 4
                // checkboxes.
                $table->boolean('consent_accepted')->default(false)->after('declaration_accepted');
            }
        });
    }

    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            foreach (['consent_accepted', 'declaration_accepted'] as $column) {
                if (Schema::hasColumn('riders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
