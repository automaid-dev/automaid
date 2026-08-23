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
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();
            $table->string('title')->nullable();
            $table->string('image_path');
            // Where the tap should open — an external URL or a deep
            // link the app can recognize. Left free-form (nullable) so
            // a banner can be purely informational with nothing to tap.
            $table->string('link')->nullable();
            // 'customer' or 'merchantrider' — kept as one shared value
            // for both the merchant and rider roles rather than three
            // separate targets, since they're one app (automaid_merchantrider)
            // and the person managing banners thinks of it as one
            // audience, not two.
            $table->string('target', 20)->default('customer');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('created_by')->index()->nullable();
            $table->unsignedInteger('updated_by')->index()->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
