<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Payment gateway service charge: a % of the final amount (after voucher
 * and SST), set per gateway by admin (Settings → Commission & Fee →
 * Payment Gateway). 0% = no charge (the default).
 *
 * Added as its own line ("Payment gateway service charge") and included
 * in orders.grand_total, so the gateway, the transaction record and the
 * receipt all show the same total. Applied to every online payment:
 * bookings, dry cleaning, bag purchases, subscription sign-up/upgrade
 * and monthly renewals.
 */
class GatewayFee
{
    public const LABEL = 'Payment gateway service charge';

    public static function percent(?string $gateway, ?Setting $setting = null): float
    {
        $setting ??= Setting::find(1);
        if (!$setting) {
            return 0.0;
        }
        $column = ($gateway === 'gkash' ? 'gkash' : 'fiuu') . '_service_charge_percent';
        return max(0.0, (float) ($setting->$column ?? 0));
    }

    public static function forAmount(float $amount, ?string $gateway, ?Setting $setting = null): float
    {
        $pct = self::percent($gateway, $setting);
        if ($pct <= 0 || $amount <= 0) {
            return 0.0;
        }
        return round($amount * $pct / 100, 2);
    }

    /**
     * Adds the charge to an order right before its payment page is
     * created (Order::paymentUrlOrCancel). Safe to call twice — an order
     * that already carries a fee is left alone.
     */
    public static function apply(Order $order): void
    {
        if ((float) $order->gateway_fee > 0 || (float) $order->grand_total <= 0) {
            return;
        }
        if (!Schema::hasColumn('orders', 'gateway_fee')) {
            return; // migration not run yet
        }
        $pct = self::percent($order->payment_gateway);
        $fee = self::forAmount((float) $order->grand_total, $order->payment_gateway);
        if ($fee <= 0) {
            return;
        }

        $order->gateway_fee = $fee;
        $order->gateway_fee_percent = $pct;
        $order->grand_total = round((float) $order->grand_total + $fee, 2);
        $order->save();

        $payment = $order->payment;
        if ($payment && !$payment->is_paid) {
            $payment->amount = $order->grand_total;
            $payment->save();
        }
    }

    /** % per payment type, for the app's order summary screens. */
    public static function forApp(?Setting $setting = null): array
    {
        $setting ??= Setting::find(1);
        if (!$setting) {
            return [];
        }
        return [
            'label' => self::LABEL,
            'booking' => self::percent($setting->payment_gateway_booking ?? 'fiuu', $setting),
            'dry_cleaning' => self::percent($setting->payment_gateway_dry_cleaning ?? 'fiuu', $setting),
            'bag_purchase' => self::percent($setting->payment_gateway_bag_purchase ?? 'fiuu', $setting),
            'subscription' => self::percent($setting->payment_gateway_subscription ?? 'fiuu', $setting),
        ];
    }
}
