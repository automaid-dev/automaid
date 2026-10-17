<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentRecurring;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * In-app account deletion (required by Apple App Store guideline
 * 5.1.1(v), and the PDPA right to have personal data removed).
 *
 * What happens:
 *  - Refused while the person has a paid booking still in progress
 *    (as customer, or as the accepted rider/merchant) — finishing or
 *    cancelling it needs them reachable.
 *  - Subscriptions are cancelled and stored card tokens deactivated, so
 *    nothing is ever charged again.
 *  - Personal data is wiped from users / riders / merchants / addresses
 *    (name, email, phone, IC, address, location, bank account, emergency
 *    contact, IC/licence/grant photos), the audit trail holding old copies
 *    of it is removed, all login tokens are revoked and the account is
 *    soft-deleted.
 *  - Orders, payments and transactions are KEPT (without the person's
 *    details) because they are financial records the business must retain.
 *  - The same email/phone can register again later as a new account.
 */
class AccountDeletionService
{
    /** @return string|null reason it can't be deleted yet, or null if it can */
    public function blockingReason(User $user): ?string
    {
        $inProgress = fn ($q) => $q
            ->whereNotNull('booking_id')
            ->where('status', Order::PAID)
            ->whereDoesntHave('order_statuses', fn ($s) => $s
                ->where('code', OrderStatus::CUSTOMER_ORDER_DELIVERED)
                ->where('is_done', true));

        $asCustomer = Order::where('user_id', $user->id)->where($inProgress)->count();
        if ($asCustomer > 0) {
            return "You have {$asCustomer} booking(s) still in progress. Please wait until they are delivered, or contact support, before deleting your account.";
        }

        $asPartner = Order::where($inProgress)
            ->where(fn ($q) => $q
                ->whereHas('rider', fn ($a) => $a->where('accepted_by', $user->id))
                ->orWhereHas('merchant', fn ($a) => $a->where('accepted_by', $user->id)))
            ->count();
        if ($asPartner > 0) {
            return "You still have {$asPartner} job(s) in progress. Please complete them, or contact support, before deleting your account.";
        }

        return null;
    }

    public function delete(User $user, ?string $reason = null): void
    {
        $id = $user->id;
        $files = [];

        DB::transaction(function () use ($user, $id, &$files) {
            // 1. Stop all billing.
            Subscription::where('user_id', $id)
                ->whereIn('status', [Subscription::ACTIVE, Subscription::PENDING])
                ->update(['status' => Subscription::CANCELLED, 'updated_at' => now()]);
            PaymentRecurring::whereIn('subscription_id', Subscription::where('user_id', $id)->select('id'))
                ->whereIn('status', [PaymentRecurring::SUBSCRIPTION, PaymentRecurring::SUBSCRIPTION_RENEWAL])
                ->update(['status' => PaymentRecurring::CANCELLED, 'updated_at' => now()]);

            // 2. Wipe personal data. Query-builder updates on purpose: they
            //    don't create audit rows containing the old values.
            $this->scrub('users', ['id' => $id], [
                'name' => 'Deleted user',
                'email' => 'deleted-' . $id . '-' . Str::lower(Str::random(8)) . '@deleted.invalid',
                'password' => bcrypt(Str::random(40)),
                'first_name' => null, 'last_name' => null, 'gender' => null, 'dob' => null,
                'race' => null, 'religion' => null, 'icno' => null, 'id_type' => null,
                'country_code_mobile' => null, 'mobile_no' => null, 'phone_no' => null,
                'unit_no' => null, 'floor' => null, 'block' => null,
                'address_line_1' => null, 'address_line_2' => null, 'address_line_3' => null,
                'd_address_line_1' => null, 'd_address_line_2' => null, 'd_address_line_3' => null,
                'postcode' => null, 'city' => null, 'd_postcode' => null, 'd_city' => null,
                'latitude' => null, 'longitude' => null, 'avatar' => null,
                'device_id' => null, 'api_token' => null, 'remember_token' => null,
                'provider' => null, 'provider_id' => null, 'last_login_ip' => null,
                'is_active' => false, 'is_duty' => false, 'status' => User::INACTIVE,
            ]);

            $rider = DB::table('riders')->where('user_id', $id)->first();
            if ($rider) {
                foreach (['ic_front', 'ic_back', 'license_front', 'license_back', 'jpj_grant'] as $f) {
                    if (!empty($rider->$f)) $files[] = $rider->$f;
                }
                $this->scrub('riders', ['user_id' => $id], [
                    'bank_name' => null, 'bank_no' => null,
                    'emergency_name' => null, 'emergency_phone' => null, 'emergency_relation' => null, 'country_code_emergency' => null,
                    'plate_no' => null, 'vehicle_make' => null, 'vehicle_model' => null, 'vehicle_color' => null, 'vehicle_color_other' => null,
                    'ic_front' => null, 'ic_back' => null, 'license_front' => null, 'license_back' => null, 'jpj_grant' => null,
                    'deleted_at' => now(),
                ]);
            }

            $merchant = DB::table('merchants')->where('user_id', $id)->first();
            if ($merchant) {
                foreach (['ic_front', 'ic_back', 'ssm_cert'] as $f) {
                    if (!empty($merchant->$f)) $files[] = $merchant->$f;
                }
                // Company name/SSM/outlet address are business records and stay.
                $this->scrub('merchants', ['user_id' => $id], [
                    'bank_name' => null, 'bank_no' => null, 'ic_front' => null, 'ic_back' => null,
                    'deleted_at' => now(),
                ]);
            }

            $this->scrub('addresses', ['user_id' => $id], [
                'address_title' => null, 'unit_no' => null, 'floor' => null, 'block' => null,
                'address_line_1' => null, 'address_line_2' => null, 'address_line_3' => null,
                'postcode' => null, 'city' => null, 'latitude' => null, 'longitude' => null,
                'status' => 'inactive', 'deleted_at' => now(),
            ]);

            // 3. Old copies of the data in the audit trail.
            if (Schema::hasTable('audits')) {
                DB::table('audits')
                    ->where(fn ($q) => $q
                        ->where(fn ($a) => $a->where('auditable_type', User::class)->where('auditable_id', $id))
                        ->orWhere(fn ($a) => $a->where('auditable_type', \App\Models\Rider::class)->whereIn('auditable_id', DB::table('riders')->where('user_id', $id)->select('id')))
                        ->orWhere(fn ($a) => $a->where('auditable_type', \App\Models\Merchant::class)->whereIn('auditable_id', DB::table('merchants')->where('user_id', $id)->select('id')))
                        ->orWhere(fn ($a) => $a->where('auditable_type', \App\Models\Address::class)->whereIn('auditable_id', DB::table('addresses')->where('user_id', $id)->select('id'))))
                    ->delete();
            }

            // 4. Sign out everywhere and close the account.
            $user->tokens()->delete();
            DB::table('users')->where('id', $id)->update(['deleted_at' => now()]);
        });

        // Photos in S3 — after commit, best effort.
        foreach ($files as $path) {
            try {
                Storage::disk('s3')->delete($path);
            } catch (\Throwable $e) {
                Log::warning('Account deletion: could not delete file', ['user_id' => $id, 'path' => $path, 'error' => $e->getMessage()]);
            }
        }

        Log::info('Account deleted by user', ['user_id' => $id, 'reason' => $reason]);
        try {
            (new TelegramService())->notifyAdmin("🗑 <b>Account deleted</b>\nUser #{$id} deleted their account in the app." . ($reason ? "\nReason: " . e($reason) : ''));
        } catch (\Throwable $e) {
            // alert is optional
        }
    }

    /** Update only the columns that exist on this server's table. */
    protected function scrub(string $table, array $where, array $values): void
    {
        $columns = array_flip(Schema::getColumnListing($table));
        $values = array_intersect_key($values, $columns);
        if (isset($columns['updated_at'])) {
            $values['updated_at'] = now();
        }
        if ($values) {
            DB::table($table)->where($where)->update($values);
        }
    }
}
