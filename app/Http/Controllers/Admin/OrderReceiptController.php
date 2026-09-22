<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;

class OrderReceiptController extends Controller
{
    /**
     * Mirrors BookingReceiptScreen's row set and rules exactly (same
     * order, same "only show if non-zero/non-null" conditions), so
     * admin sees precisely what the customer would see in the app —
     * not a re-derived approximation of it.
     */
    public function show(Order $order)
    {
        $order->load(['booking', 'delivered']);
        $setting = Setting::find(1);

        return view('admin.order-receipt', [
            'order' => $order,
            'booking' => $order->booking,
            'setting' => $setting,
            'isDelivered' => (bool) $order->delivered,
        ]);
    }
}
