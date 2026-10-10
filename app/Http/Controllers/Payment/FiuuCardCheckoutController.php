<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentGateway\FiuuPaymentService;

/**
 * GET /payment/fiuu/card/{order} (signed, expiring link) — opened in the
 * app's payment WebView for subscription card payments. Auto-posts to
 * Fiuu's seamless card form so the customer lands straight on "add card"
 * instead of Fiuu's channel list.
 *
 * Deliberately NOT under /webhook/: the app treats any /webhook/fiuu/
 * URL as "payment finished".
 */
class FiuuCardCheckoutController extends Controller
{
    const SUBSCRIPTION_TYPES = [
        Order::SUBSCRIPTION,
        Order::SUBSCRIPTION_UPGRADE,
        Order::SUBSCRIPTION_UPDATE,
    ];

    public function show(Order $order)
    {
        if ($order->status !== Order::PENDING || !in_array($order->order_type, self::SUBSCRIPTION_TYPES, true)) {
            return response()->view('payment.return', [
                'success' => false,
                'title' => 'Payment link expired',
                'message' => 'This payment has already been completed or cancelled. Please return to the app.',
            ], 410);
        }

        $description = match ($order->order_type) {
            Order::SUBSCRIPTION_UPGRADE => 'Subscription upgrade',
            Order::SUBSCRIPTION_UPDATE => 'Subscription card update',
            default => 'Subscription',
        };

        $form = FiuuPaymentService::recurring()->seamlessCardForm($order, $description);

        return view('payment.fiuu-card', [
            'action' => $form['action'],
            'fields' => $form['fields'],
            'amount' => $form['fields']['mpsamount'],
        ]);
    }
}
