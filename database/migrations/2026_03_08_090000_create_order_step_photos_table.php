<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generic photo+remark storage for any handoff step in the order
     * lifecycle — rider pickup (from customer, includes delivery to
     * wash outlet in the same action), merchant bag receive, merchant
     * wash complete, rider pickup from outlet. The final
     * delivery-to-customer step already has its own image1/2/3 columns
     * on order_completes — this table is for the steps that had no
     * photo capture at all before this patch, kept generic (one row per
     * photo) rather than adding bespoke image columns to 4 different
     * existing tables.
     */
    public function up(): void
    {
        Schema::create('order_step_photos', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();
            $table->unsignedInteger('order_id');
            $table->string('code', 50); // order status code this photo belongs to (e.g. '13', '23', '24', '14')
            $table->string('image_path', 250)->nullable();
            $table->text('remark')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_step_photos');
    }
};
