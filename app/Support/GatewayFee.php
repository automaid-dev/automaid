<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Payment gateway service charge: a % of the final amount (after voucher
 * and SST), set by admin per gateway AND per payment kind (Settings →
 * Commission & Fee → Payment Gateway). 0% = no charge (the default).
 *
 *   booking      → {gateway}_service_charge_percent
 *                  (bookings, dry cleaning, bag purchases)
 *   subscription → {gateway}_subscription_service_charge_percent
 *                  (sign-up, card update, upgrade, monthly renewal)
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

    public const BOOKING = 'booking';
    public const SUBSCRIPTION = 'subscription';

    /** Order types charged at the subscription rate. */
    public const SUBSCRIPTION_ORDER_TYPES = [
        Order::SUBSCRIPTION,
        Order::SUBSCRIPTION_RENEWAL,
        Order::SUBSCRIPTION_UPDATE,
        Order::SUBSCRIPTION_UPGRADE,
    ];

    public static function kindForOrderType(?string $orderType): string
    {
        return in_array($orderType, self::SUBSCRIPTION_ORDER_TYPES, true) ? self::SUBSCRIPTION : self::BOOKING;
    }

    public static function percent(?string $gateway, ?Setting $setting = null, string $kind = self::BOOKING): float
    {
        $setting ??= Setting::find(1);
        if (!$setting) {
            return 0.0;
        }
        $gw = in_array($gateway, ['gkash', 'revenuemonster'], true) ? $gateway : 'fiuu';
        $bookingColumn = "{$gw}_service_charge_percent";
        $column = $kind === self::SUBSCRIPTION ? "{$gw}_subscription_service_charge_percent" : $bookingColumn;
        // Before the migration runs, subscriptions fall back to the old single rate.
        $value = $setting->getAttribute($column) ?? $setting->getAttribute($bookingColumn);
        return max(0.0, (float) ($value ?? 0));
    }

    public static function forAmount(float $amount, ?string $gateway, ?Setting $setting = null, string $kind = self::BOOKING): float
    {
        $pct = self::percent($gateway, $setting, $kind);
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
        $kind = self::kindForOrderType($order->order_type);
        $pct = self::percent($order->payment_gateway, null, $kind);
        $fee = self::forAmount((float) $order->grand_total, $order->payment_gateway, null, $kind);
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
            'subscription' => self::percent($setting->payment_gateway_subscription ?? 'fiuu', $setting, self::SUBSCRIPTION),
        ];
    }
}
