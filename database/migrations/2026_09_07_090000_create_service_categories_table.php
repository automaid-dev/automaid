<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('hashslug', 20)->unique();
            $table->string('name', 100); // "Dry Cleaning", "Shoe Cleaning", etc — must match merchants.service_categories' string values exactly
            $table->string('slug', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->index()->nullable();
            $table->unsignedInteger('updated_by')->index()->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Seeded directly here rather than left for admin to create —
        // these 4 names must match register_merchant_screen.dart's
        // _serviceCategoryOptions exactly for merchant capability
        // matching to work, so they're fixed data, not admin-authored
        // content. Only "Dry Cleaning" gets a per-piece item catalog
        // for now (the other 3 aren't being launched with this feature);
        // is_active lets admin quietly enable one later without a
        // migration once its own item catalog exists.
        $now = now();
        \Illuminate\Support\Facades\DB::table('service_categories')->insert([
            ['hashslug' => \Illuminate\Support\Str::random(15), 'name' => 'Dry Cleaning', 'slug' => 'dry-cleaning', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['hashslug' => \Illuminate\Support\Str::random(15), 'name' => 'Shoe Cleaning', 'slug' => 'shoe-cleaning', 'is_active' => false, 'created_at' => $now, 'updated_at' => $now],
            ['hashslug' => \Illuminate\Support\Str::random(15), 'name' => 'Helmet Cleaning', 'slug' => 'helmet-cleaning', 'is_active' => false, 'created_at' => $now, 'updated_at' => $now],
            ['hashslug' => \Illuminate\Support\Str::random(15), 'name' => 'Wash & Dry', 'slug' => 'wash-and-dry', 'is_active' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('service_categories');
    }
};
