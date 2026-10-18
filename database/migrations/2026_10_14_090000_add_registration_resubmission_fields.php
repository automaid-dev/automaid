<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets admin send a rider/merchant registration back for new document
 * photos (blurry, expired, expiring soon...) and the applicant re-upload
 * them in the app. See App\Support\RegistrationDocuments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'rejection_details')) {
                // {documents: [...], issues: [...], note, can_resubmit, rejected_at, rejected_by}
                $table->json('rejection_details')->nullable()->after('rejected_reason');
            }
            if (!Schema::hasColumn('users', 'resubmitted_at')) {
                $table->timestamp('resubmitted_at')->nullable()->after('rejection_details');
            }
            if (!Schema::hasColumn('users', 'resubmission_count')) {
                $table->unsignedSmallInteger('resubmission_count')->default(0)->after('resubmitted_at');
            }
        });

        Schema::table('riders', function (Blueprint $table) {
            if (!Schema::hasColumn('riders', 'license_expiry')) {
                $table->date('license_expiry')->nullable()->after('license_back');
            }
            if (!Schema::hasColumn('riders', 'jpj_grant_expiry')) {
                $table->date('jpj_grant_expiry')->nullable()->after('jpj_grant');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['rejection_details', 'resubmitted_at', 'resubmission_count'] as $c) {
                if (Schema::hasColumn('users', $c)) $table->dropColumn($c);
            }
        });
        Schema::table('riders', function (Blueprint $table) {
            foreach (['license_expiry', 'jpj_grant_expiry'] as $c) {
                if (Schema::hasColumn('riders', $c)) $table->dropColumn($c);
            }
        });
    }
};
