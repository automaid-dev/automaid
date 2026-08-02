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
        Schema::table('commission_transactions', function (Blueprint $table) {
            $table->boolean('is_paid')->default(0)->after('desc');
            $table->datetime('paid_at')->nullable()->after('is_paid');
            $table->unsignedInteger('paid_by')->index()->nullable()->after('paid_at');
            $table->decimal('final_amount', 8, 2)->nullable()->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commission_transactions', function (Blueprint $table) {
            //
        });
    }
};
