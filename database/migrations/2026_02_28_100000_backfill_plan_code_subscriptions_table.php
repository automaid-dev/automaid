<?php

use App\Models\Setting;
use App\Models\Subscription;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Backfills plan_code for existing subscriptions created before that
     * column was actually being saved (SubscriptionController::placeOrder
     * validated plan_code as required but never persisted it — fixed
     * separately in the same patch as this migration). Without this,
     * every subscription created before the fix would show as "Legacy
     * Plan" / no plan matched in the app, even though the customer did
     * pick and pay for a specific tier.
     *
     * Inferred from the linked payment's amount, matched against the
     * plan prices currently in Settings — not perfectly precise if
     * prices have changed since a given subscription was created, but
     * far better than leaving plan_code permanently null for anyone who
     * subscribed before this fix.
     */
    public function up(): void
    {
        $setting = Setting::find(1);
        if (!$setting) {
            return;
        }

        $priceMap = [
            Subscription::BRONZE => (float) ($setting->subscription_bronze_price ?? 0),
            Subscription::SILVER => (float) ($setting->subscription_silver_price ?? 0),
            Subscription::PLATINUM => (float) ($setting->subscription_platinum_price ?? 0),
        ];

        Subscription::whereNull('plan_code')
            ->whereHas('payment')
            ->with('payment')
            ->get()
            ->each(function (Subscription $subscription) use ($priceMap) {
                $amount = (float) ($subscription->payment->amount ?? 0);
                if ($amount <= 0) {
                    return;
                }

                foreach ($priceMap as $code => $price) {
                    // small tolerance for rounding
                    if ($price > 0 && abs($amount - $price) < 0.01) {
                        $subscription->plan_code = $code;
                        $subscription->save();
                        break;
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally a no-op — this migration only fills in previously-null
     * data inferred from existing records; rolling it back would just
     * discard a best-effort correction with no real "before" state to
     * restore to.
     */
    public function down(): void
    {
        //
    }
};
