<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();
            $table->unsignedInteger('user_id')->index();
            $table->string('role', 20); // 'rider' or 'merchant' — the settled user's role at the time of settlement, so a receipt still reads correctly even if roles ever changed later
            $table->decimal('gross_amount', 10, 2); // sum of the selected transactions' final_amount, before deductions
            $table->decimal('total_deductions', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2); // gross_amount - total_deductions — the actual amount transferred
            $table->string('bank_transaction_id'); // external bank/e-wallet transfer reference — required, this is the audit trail admin asked for
            $table->text('notes')->nullable();
            $table->datetime('paid_at');
            $table->unsignedInteger('paid_by')->index(); // admin user who performed the settlement
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_settlements');
    }
};
