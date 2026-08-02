<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Subscription;
use Illuminate\Http\Request;

/**
 * Read-only endpoint exposing the current subscription package prices
 * and order quotas — everything here is admin-editable in
 * Settings > Subscription Fees/Discounts. No auth required (same as
 * /setting), since a customer needs to see plan pricing before logging in
 * / before subscribing.
 */
class SubscriptionPlanController extends Controller
{
    /**
     * [index description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function index(Request $request)
    {
        try {
            $setting = Setting::find(1);

            $plans = [
                [
                    'code' => Subscription::BRONZE,
                    'name' => Subscription::planLabel(Subscription::BRONZE),
                    'price' => (float) ($setting->subscription_bronze_price ?? 0),
                    'order_quota' => $setting->subscription_bronze_orders !== null
                        ? (int) $setting->subscription_bronze_orders
                        : null,
                ],
                [
                    'code' => Subscription::SILVER,
                    'name' => Subscription::planLabel(Subscription::SILVER),
                    'price' => (float) ($setting->subscription_silver_price ?? 0),
                    'order_quota' => $setting->subscription_silver_orders !== null
                        ? (int) $setting->subscription_silver_orders
                        : null,
                ],
                [
                    'code' => Subscription::PLATINUM,
                    'name' => Subscription::planLabel(Subscription::PLATINUM),
                    'price' => (float) ($setting->subscription_platinum_price ?? 0),
                    'order_quota' => null, // unlimited
                ],
            ];

            return response()->json([
                'status' => true,
                'message' => 'Successfully get subscription plans.',
                'data' => [
                    'plans' => $plans,
                    // Free-bag-per-order allowance applies the same way across
                    // all three plans — only the first bag in an order is free;
                    // additional bags are charged wash_fee + delivery_price
                    // each. Surfaced here so the app doesn't need a second call
                    // to /setting just to show this on the plan picker.
                    'free_bag_wash' => (int) ($setting->total_bag_free_wash ?? 1),
                    'free_bag_delivery' => (int) ($setting->total_bag_free_delivery ?? 1),
                ],
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
