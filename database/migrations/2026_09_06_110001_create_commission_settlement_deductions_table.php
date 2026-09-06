<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_settlement_deductions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('commission_settlement_id')->index();
            $table->string('type', 30); // penalty / chargeback / refund / adjustment
            $table->string('description')->nullable();
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_settlement_deductions');
    }
};
