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
        Schema::table('states', function (Blueprint $table) {
            // Broad coverage flag — if true, EVERY city in this state
            // is treated as covered, regardless of that city's own
            // flag. Lets admin cover a whole state in one toggle
            // instead of enumerating every city in it.
            $table->boolean('is_service_covered')->default(false)->after('name');
        });

        Schema::table('cities', function (Blueprint $table) {
            // Fine-grained override — lets admin cover specific cities
            // even in a state that isn't broadly covered, or (in
            // principle) exclude one even in a covered state, though
            // the coverage check itself treats "state covered" as
            // covering everything in it regardless of this flag.
            $table->boolean('is_service_covered')->default(false)->after('name');
        });

        /**Schema::table('waiting_lists', function (Blueprint $table) {
            $table->string('postcode', 20)->nullable()->after('city');
        });*/
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('states', function (Blueprint $table) {
            $table->dropColumn('is_service_covered');
        });
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('is_service_covered');
        });
        /**Schema::table('waiting_lists', function (Blueprint $table) {
            $table->dropColumn('postcode');
        });*/
    }
};
