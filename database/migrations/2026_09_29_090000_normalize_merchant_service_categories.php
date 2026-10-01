<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Merchants created or edited in the admin panel stored service
 * categories as keys ('dry_cleaning', 'wash_dry', ...), while the
 * merchant app and the auto-assign job use the labels ('Dry Cleaning',
 * 'Wash & Dry', ...). Auto-assign's whereJsonContains('service_categories',
 * 'Dry Cleaning') therefore never matched admin-created merchants.
 * Converts any old keys to the labels. Safe to re-run.
 */
return new class extends Migration
{
    private array $map = [
        'dry_cleaning' => 'Dry Cleaning',
        'shoe_cleaning' => 'Shoe Cleaning',
        'helmet_cleaning' => 'Helmet Cleaning',
        'wash_dry' => 'Wash & Dry',
    ];

    public function up(): void
    {
        DB::table('merchants')->whereNotNull('service_categories')->orderBy('id')
            ->each(function ($row) {
                $values = json_decode($row->service_categories, true);
                if (is_string($values)) {
                    $values = json_decode($values, true); // double-encoded
                }
                if (!is_array($values)) {
                    return;
                }
                $fixed = array_values(array_unique(array_map(fn ($v) => $this->map[$v] ?? $v, $values)));
                if ($fixed !== $values) {
                    DB::table('merchants')->where('id', $row->id)
                        ->update(['service_categories' => json_encode($fixed)]);
                }
            });
    }

    public function down(): void
    {
        // Not reversible — the old keys never worked with auto-assign.
    }
};
