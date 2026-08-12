<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Repairs AssignJob rows created by the admin's manual rider/merchant
     * assignment (EditOrder.php) before this patch — those never set
     * is_queue=true, which both the rider and merchant apps require for
     * their own pending-acceptance code (11 / 21 respectively) before a
     * job shows up in the dashboard at all. Without this backfill, any
     * order manually assigned before this deploy stays invisible to the
     * assigned rider/merchant forever, even after the code fix — a fresh
     * deploy only prevents the bug for new assignments going forward,
     * it doesn't touch rows that already exist.
     *
     * Scoped tightly: only rows still awaiting acceptance (is_accepted
     * is null/false) for the two pending-acceptance codes, that aren't
     * already correctly queued. Accepted/completed jobs are untouched.
     */
    public function up(): void
    {
        DB::table('assign_jobs')
            ->whereIn('code', ['11', '21'])
            ->where(function ($query) {
                $query->whereNull('is_accepted')->orWhere('is_accepted', false);
            })
            ->where(function ($query) {
                $query->whereNull('is_queue')->orWhere('is_queue', false);
            })
            ->update(['is_queue' => true]);
    }

    /**
     * Reverse the migrations.
     *
     * Deliberately a no-op — reversing this would put currently-broken
     * (invisible-to-the-rider) jobs back into that state, which is never
     * what anyone would want.
     */
    public function down(): void
    {
        //
    }
};
