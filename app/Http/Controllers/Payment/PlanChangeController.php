<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\SubscriptionPlanChangeService;

/**
 * Result page the app opens for a GKash / Revenue Monster plan upgrade.
 * Opening it charges the saved card once (see chargeUpgrade); the app
 * then re-checks the order status itself, like any other payment.
 * Lives under /webhook/gkash/ and /webhook/rm/ so the app's payment
 * screen recognises it as "finished".
 */
class PlanChangeController extends Controller
{
    public function charge($order, string $key)
    {
        ignore_user_abort(true);
        set_time_limit(120);

        $order = Order::find($order);
        if (!$order || !hash_equals(SubscriptionPlanChangeService::resultKey((int) $order->id), $key)) {
            abort(404);
        }

        $outcome = (new SubscriptionPlanChangeService())->chargeUpgrade($order);

        return view('payment.return', [
            'success' => $outcome === 'paid',
            'title' => match ($outcome) {
                'paid' => 'Plan upgraded',
                'failed' => 'Upgrade not completed',
                default => 'Payment being confirmed',
            },
            'message' => match ($outcome) {
                'paid' => 'We charged your saved card and your new plan is active. You can return to the app.',
                'failed' => 'Your card could not be charged, so your plan was not changed. You can return to the app.',
                default => 'We are confirming the payment with your bank. Your plan will change once it is confirmed. You can return to the app.',
            },
        ]);
    }
}
