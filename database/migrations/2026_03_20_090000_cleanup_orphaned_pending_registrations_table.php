<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Repairs registrations left orphaned by failures fixed alongside
     * this migration (the business_option/id_type integer mismatches,
     * and the missing transaction wrap that let a User row survive
     * even when the matching Rider/Merchant profile failed to create).
     * These users are stuck in 'pending' with no completed profile —
     * blocked from re-registering (their email is "taken"), but with
     * no way to actually finish either.
     *
     * Soft-deletes any such orphaned user, which the existing
     * soft-delete-aware registration flow (RiderController/
     * MerchantController::register) already knows how to handle
     * correctly — restoring and reusing that same row the next time
     * someone registers with that email, this time completing
     * successfully now that the underlying bugs are fixed.
     */
    public function up(): void
    {
        // Orphaned pending merchants — has the merchant role, status
        // pending, but no row in `merchants` for them at all.
        DB::table('users')
            ->where('status', 'pending')
            ->whereNull('deleted_at')
            ->whereIn('id', function ($query) {
                $query->select('model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', 'merchant')
                    ->where('model_has_roles.model_type', 'App\\Models\\User');
            })
            ->whereNotIn('id', function ($query) {
                $query->select('user_id')->from('merchants')->whereNotNull('user_id');
            })
            ->update(['deleted_at' => now()]);

        // Same for orphaned pending riders.
        DB::table('users')
            ->where('status', 'pending')
            ->whereNull('deleted_at')
            ->whereIn('id', function ($query) {
                $query->select('model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', 'rider')
                    ->where('model_has_roles.model_type', 'App\\Models\\User');
            })
            ->whereNotIn('id', function ($query) {
                $query->select('user_id')->from('riders')->whereNotNull('user_id');
            })
            ->update(['deleted_at' => now()]);
    }

    public function down(): void
    {
        //
    }
};
