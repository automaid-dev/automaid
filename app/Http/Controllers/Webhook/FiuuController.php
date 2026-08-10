<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Mail\NewOrderEmail;
use App\Mail\PurchaseBagEmail;
use App\Mail\PurchaseSubscriptionEmail;
use App\Models\Activity;
use App\Models\AssignJob;
use App\Models\Bag;
use App\Models\Booking;
use App\Models\Order;
use App\Models\OrderBooking;
use App\Models\OrderStatus;
use App\Models\Payment;
use App\Models\PaymentRecurring;
use App\Models\Qrcode;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Models\VoucherUser;
use App\Services\OneSignalService;
use App\Services\PaymentGateway\FiuuPaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FiuuController extends Controller
{
    public $rms;

    /**
     * [__construct description]
     */
    public function __construct()
    {
        $this->rms = new FiuuPaymentService();
    }

    /**
     * [getReturn description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getReturn(Request $request)
    {
        // Diagnostic log: confirms whether Fiuu's server-to-server
        // callback is actually reaching this endpoint at all. Check
        // storage/logs/laravel.log for 'Fiuu webhook hit: getReturn' if
        // payments show successful on Fiuu's own page but never flip
        // to paid in this app — an empty log here for a known test
        // means the call never arrived (check the Notification URL
        // configured in the Fiuu merchant dashboard, and that this
        // server is reachable over HTTPS from Fiuu's servers).
        Log::info('Fiuu webhook hit: getReturn', ['ip' => $request->ip(), 'body' => $request->getContent()]);

        // local environment
        if (app()->environment('local')) {
            $data = [
                'orderid' => $request->order_id,
                'status'  => '00',
                'channel' => 'FPX_MB2U',
                'extraP'   => json_encode([
                    'token'   => 'TK_2910_71512556822471214514',
                    'ccbrand' => 'Visa',
                    'cclast4' => '1111',
                    'cctype'  => 'Credit',
                ]),
            ];
            $checkPayment = true;
        }

        // server environment
        else {

            // get data request
            $content = $request->getContent();
            parse_str($content, $data);

            // verify data
            $checkPayment = $this->rms->checkVerifySignature($data);        
        }

        // check payment
        if ($checkPayment) {

            // check data
            $order              = Order::find($data['orderid']);
            Log::info('Fiuu payment check', [
                'orderid_requested' => $data['orderid'] ?? null,
                'order_found' => $order ? true : false,
                'order_type' => $order->order_type ?? null,
                'order_status' => $order->status ?? null,
                'check_payment' => $checkPayment,
                'payment_status_code' => $data['status'] ?? null,
            ]);
            $subscription       = $order->subscription;

            // status success
            if ($data['status'] == '00') {

                // order type purchase bag
                if ($order->order_type == Order::PURCHASE_BAG) {

                    // return data
                    $data2['order'] = $order->load([
                        'user', 
                        'payment',
                        'bag_purchases',
                    ]);
                    return response()->json([
                        'status' => true,
                        'message' => 'Successfully purchase bag and make payment.',
                        'data' => $data2,
                    ]);
                }

                // order type booking
                else if ($order->order_type == Order::BOOKING) {

                    // get data booking
                    $booking = $order->booking;

                    // return data
                    $data2['booking'] = $booking->load([
                        'order',
                        'user', 
                        'pickup_location',
                        'customer_order_status.status',
                    ]);
                    return response()->json([
                        'status' => true,
                        'message' => 'Successfully booking and make payment.',
                        'data' => $data2,
                    ]);
                }

                // order type subscription
                else if ($order->order_type == Order::SUBSCRIPTION) {

                    // return data
                    $data2['subscription'] = $subscription->load(['order', 'bags', 'payment']);
                    return response()->json([
                        'status' => true,
                        'message' => 'Successfully subscribe and make payment.',
                        'data' => $data2,
                    ]);
                }

                // order type subscription update (update credit card)
                // no need new activity
                else if ($order->order_type == Order::SUBSCRIPTION_UPDATE) {

                    // return data
                    $data2['subscription'] = $subscription->load(['order', 'bags', 'payment']);
                    return response()->json([
                        'status' => true,
                        'message' => 'Successfully subscribe and make payment.',
                        'data' => $data2,
                    ]);
                }

                // redirect to result page
                return response()->json([
                    'status' => true,
                    'message' => 'Payment successfully made. Thank you!',
                ]);
            }

            // status failed
            else {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment Failed.',
                ]);
            }

        }
        else {
            return response()->json([
                'status' => false,
                'message' => 'Payment failed.',
            ]);
        }
    }

    /**
     * [notification description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getNotification(Request $request)
    {
        // Diagnostic log: confirms whether Fiuu's server-to-server
        // callback is actually reaching this endpoint at all. Check
        // storage/logs/laravel.log for 'Fiuu webhook hit: getNotification' if
        // payments show successful on Fiuu's own page but never flip
        // to paid in this app — an empty log here for a known test
        // means the call never arrived (check the Notification URL
        // configured in the Fiuu merchant dashboard, and that this
        // server is reachable over HTTPS from Fiuu's servers).
        Log::info('Fiuu webhook hit: getNotification', ['ip' => $request->ip(), 'body' => $request->getContent()]);

        // local environment
        if (app()->environment('local')) {
            $data = [
                'orderid' => $request->order_id,
                'status'  => '00',
                'channel' => 'FPX_MB2U',
                'extraP'   => json_encode([
                    'token'   => 'TK_2910_71512556822471214514',
                    'ccbrand' => 'Visa',
                    'cclast4' => '1111',
                    'cctype'  => 'Credit',
                ]),
            ];
            $checkPayment = true;
        }

        // server environment
        else {

            // get data request
            $content = $request->getContent();
            parse_str($content, $data);

            // verify data
            $checkPayment = $this->rms->checkVerifySignature($data);        
        }

        // check payment    
        if ($checkPayment) {

            // check data
            $order              = Order::find($data['orderid']);
            Log::info('Fiuu payment check', [
                'orderid_requested' => $data['orderid'] ?? null,
                'order_found' => $order ? true : false,
                'order_type' => $order->order_type ?? null,
                'order_status' => $order->status ?? null,
                'check_payment' => $checkPayment,
                'payment_status_code' => $data['status'] ?? null,
            ]);
            $payment            = $order->payment;
            $bag                = $order->bag;
            $order_booking      = $order->order_booking;
            $subscription       = $order->subscription;

            // status success
            if ($data['status'] == '00') {

                // insert transaction
                $transaction = Transaction::firstOrCreate(
                    ['order_id' => $order->id, 'payment_id' => $payment->id],
                    [
                        'date' => now(),
                        'type' => $order->order_type,
                        'amount' => $order->grand_total,                    
                        'status' => Transaction::PAID, 
                    ]
                );

                // update payment status
                $payment->data = json_encode($data);
                $payment->payment_method = Payment::FIUU;
                $payment->status = Payment::PAID;
                $payment->channel = $data['channel'];
                $payment->is_paid = true;
                $payment->paid_at = now();
                $payment->save();

                // update order status
                $order->status = Order::PAID;
                $order->save();

                // order type purchase bag
                if ($order->order_type == Order::PURCHASE_BAG) {

                    if ($bag) {
                        $bag->status_payment = Bag::PAID;
                        $bag->save();
                    }

                    // auto insert qrcodes
                    if ($order->quantity > 0) {
                        for ($i = 0; $i < $order->quantity; $i++) {
                            $qr = new Qrcode();
                            $code = $qr->getNextSeriesNo();
                            Qrcode::create([
                                'series_no' => $code,
                                'user_id' => $order->user_id,
                                'status' => Qrcode::SCANNED,
                                'type' => Qrcode::AUTO,
                                'scan_at' => now(),
                                'scan_by' => $order->user_id,
                                'created_by'=> $order->user_id,
                            ]);
                        }
                    }

                    // insert activity
                    $activity = Activity::firstOrCreate(
                        [
                            'order_id' => $order->id, 
                            'user_id' => $order->user_id, 
                            'transaction_id' => $transaction->id,
                            'user_type' => 'customer',
                            'title' => 'Purchase Bag', 
                            'status' => Activity::ACTIVE
                        ],
                    );

                    // send email purchase bag
                    $user = $order->user;
                    $subject = 'Auto Maid: Invoice for your purchase';
                    $emailContent = (new PurchaseBagEmail($user->name, $subject, $order))->render();
                    $onesignal = new OneSignalService();
                    $onesignal->sendEmail(
                        $user->email,
                        $subject,
                        $emailContent,
                    );
                }

                // order type booking
                else if ($order->order_type == Order::BOOKING) {

                    // insert order booking
                    $booking = Booking::firstOrCreate(
                        [
                            'order_id' => $order->id, 
                            'user_id' => $order->user_id, 
                            'pickup_location_id' => $order_booking->pickup_location_id,
                            'pickup_date' => $order_booking->pickup_date,
                        ],
                        [
                            'pickup_start_time' => $order_booking->pickup_start_time,
                            'pickup_end_time' => $order_booking->pickup_end_time,
                            'pickup_bag_quantity' => $order_booking->pickup_bag_quantity,
                            'is_folding' => $order_booking->is_folding,
                            'washing_charge' => $order_booking->washing_charge,
                            'addon_charge' => $order_booking->addon_charge,
                            'discount' => $order_booking->discount,
                            'delivery_charge' => $order_booking->delivery_charge,
                            'tax' => $order_booking->tax,
                            'grand_total' => $order_booking->grand_total,
                            'status' => Booking::ACTIVE,
                        ]
                    );

                    // update order
                    $order->booking_id = $booking->id;
                    $order->save();

                    // check voucher
                    if ($order->voucher_code) {

                        // get voucher info
                        $voucher = Voucher::where('code', $order->voucher_code)->active()->first();

                        // check taken voucher
                        $taken = $voucher->voucher_users->count();
                        if ($taken && $taken < $voucher->usage_limit) {

                            // insert voucher user
                            $voucher_user = VoucherUser::firstOrCreate(
                                [
                                    'voucher_id' => $voucher->id, 
                                    'user_id' => $order->user_id, 
                                    'order_id' => $order->id,
                                ],
                            );
                        }
                    }

                    // insert order status
                    // 01 - Waiting rider for pickup
                    // 11 - Pending for acceptance
                    // 21 - Pending for acceptance
                    $codes = [OrderStatus::CUSTOMER_WAITING_RIDER_FOR_PICKUP, OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE, OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE];
                    foreach ($codes as $key => $code) {
                        $is_customer = ($code == OrderStatus::CUSTOMER_WAITING_RIDER_FOR_PICKUP) ? 1 : 0;
                        $status = OrderStatus::firstOrCreate(
                            ['order_id' => $order->id, 'code' => $code]
                        );

                        // add assign job of customer                
                        if ($is_customer) {
                            $assign = AssignJob::firstOrCreate([
                                'order_id' => $order->id, 
                                'code' => OrderStatus::CUSTOMER_WAITING_RIDER_FOR_PICKUP,
                                'user_id' => $order->user_id,
                                'order_status_id' => $status->id,
                            ]);
                        }
                    }        

                    // insert activity
                    $activity = Activity::firstOrCreate(
                        [
                            'order_id' => $order->id, 
                            'user_id' => $order->user_id,
                            'transaction_id' => $transaction->id,                             
                            'user_type' => 'customer',
                            'title' => 'Booking', 
                            'status' => Activity::ACTIVE
                        ],
                    );

                    // get booking info
                    $booking = $order->booking;
                    $user = $order->user;

                    // send email booking                
                    $subject = 'Auto Maid: Invoice for your order';
                    $emailContent = (new NewOrderEmail($user->name, $subject, $order))->render();
                    $onesignal = new OneSignalService();
                    $onesignal->sendEmail(
                        $user->email,
                        $subject,
                        $emailContent,
                    );

                    // assign order to rider & merchant
                    Artisan::call('automaid:assign-order-to-rider-and-merchant');
                }

                // order type subscription
                else if ($order->order_type == Order::SUBSCRIPTION) {

                    // Activate the subscription as soon as THIS payment
                    // succeeds — we're already inside the
                    // `$data['status'] == '00'` (paid) branch at this
                    // point, so the customer has genuinely paid.
                    //
                    // Previously all of this (activation, bag update,
                    // qrcode issuance, activity log, email) was nested
                    // inside `if ($token) { if ($data_rec accepted) {
                    // ... } }` — gated entirely on Fiuu returning a
                    // recurring-billing token in `extraP`. Not every
                    // payment channel returns one the same way a saved
                    // credit card does (e.g. TNG eWallet) — when it
                    // didn't, this whole block silently never ran, and
                    // the subscription stayed PENDING forever even
                    // though the customer had already paid. The token
                    // is now only used for the optional recurring-token
                    // setup below, not to gate activation itself.
                    $extra = json_decode($data['extraP'] ?? 'null', true) ?? [];
                    $token = $extra['token'] ?? null;

                    // update subscription
                    $subscription->status = Subscription::ACTIVE;
                    if ($token) {
                        $subscription->cc_brand = $extra['ccbrand'] ?? null;
                        $subscription->cc_last_four = $extra['cclast4'] ?? null;
                        $subscription->cc_type = $extra['cctype'] ?? null;
                    }
                    $subscription->save();

                    // update bag
                    if ($order->bag_subscription) {
                        $bag = $order->bag_subscription;
                        $bag->status_payment = Bag::PAID;
                        $bag->save();
                    }

                    // Set up recurring billing for future auto-renewal —
                    // optional: only happens if this payment channel
                    // actually returned a token. If not, the subscription
                    // is still active for its current cycle; it just
                    // won't auto-renew via a saved token later (falls
                    // back to whatever your renewal-reminder flow does).
                    if ($token) {
                        $by_pass = 1;
                        if (app()->environment('local') || $by_pass = 1) {
                            $data_rec = [
                                [
                                    'status' => 'accepted',
                                ]
                            ];
                        } else {
                            $data_rec = $this->rms->getPaymentRequest($order, $token);
                        }

                        if ($data_rec && $data_rec[0]['status'] == 'accepted') {
                            $recurring = PaymentRecurring::firstOrCreate(
                                [
                                    'payment_id' => $payment->id, 
                                    'subscription_id' => $subscription->id, 
                                    'transaction_id' => $transaction->id,
                                    'token' => $token, 
                                    'payment_date' => $subscription->start_date,
                                    'next_payment_date' => $subscription->end_date,
                                    'data' => json_encode($data_rec), 
                                    'amount' => $order->grand_total, 
                                    'is_paid' => true, 
                                    'status' => PaymentRecurring::SUBSCRIPTION,
                                    'status_payment' => PaymentRecurring::PAID,
                                    'cc_brand' => $extra['ccbrand'] ?? null,
                                    'cc_last_four' => $extra['cclast4'] ?? null,
                                    'cc_type' => $extra['cctype'] ?? null,
                                ],
                                [
                                    'paid_at' => now()
                                ]
                            );
                        }
                    }

                    // auto insert qrcodes
                    if ($order->quantity > 0) {
                        for ($i = 0; $i < $order->quantity; $i++) {
                            $qr = new Qrcode();
                            $code = $qr->getNextSeriesNo();
                            Qrcode::create([
                                'series_no' => $code,
                                'user_id' => $order->user_id,
                                'status' => Qrcode::SCANNED,
                                'type' => Qrcode::AUTO,
                                'scan_at' => now(),
                                'scan_by' => $order->user_id,
                                'created_by'=> $order->user_id,
                            ]);
                        }
                    }

                    // insert activity
                    $activity = Activity::firstOrCreate(
                        [
                            'order_id' => $order->id, 
                            'user_id' => $order->user_id,
                            'transaction_id' => $transaction->id,
                            'user_type' => 'customer',
                            'title' => 'Subscription', 
                            'status' => Activity::ACTIVE
                        ],
                    );

                    // send email subscription
                    $user = $order->user;
                    $subject = 'Auto Maid: Your subscription purchase is successful';
                    $emailContent = (new PurchaseSubscriptionEmail($user->name, $subject, $order))->render();
                    $onesignal = new OneSignalService();
                    $onesignal->sendEmail(
                        $user->email,
                        $subject,
                        $emailContent,
                    );
                }

                // order type subscription update (update credit card)
                // no need new activity
                else if ($order->order_type == Order::SUBSCRIPTION_UPDATE) {

                    // get token
                    $extra = json_decode($data['extraP'], true);
                    $token = $extra['token'] ?? null;

                    // process recurring 
                    if ($token) {

                        // local environment
                        $by_pass = 1;                        
                        if (app()->environment('local') || $by_pass = 1) {
                            $data_rec = [
                                [
                                    'status' => 'accepted',
                                ]
                            ];
                        }

                        // server environment
                        else {

                            // process recurring
                            $data_rec = $this->rms->getPaymentRequest($order, $token);
                        }

                        // get return data
                        if ($data_rec[0]['status'] == 'accepted') {

                            // update subscription
                            $subscription->status = Subscription::ACTIVE;
                            $subscription->cc_brand = $extra['ccbrand'] ?? null; 
                            $subscription->cc_last_four = $extra['cclast4'] ?? null; 
                            $subscription->cc_type = $extra['cctype'] ?? null; 
                            $subscription->save();

                            // check previous subscription
                            $prev_subscription = Subscription::find($subscription->previous_id);
                            if ($prev_subscription) {

                                // update subscription id at bag                            
                                if ($prev_subscription->bag) {
                                    $bag = $prev_subscription->bag;
                                    $bag->subscription_id = $subscription->id;
                                    $bag->save();
                                }

                                // update subscription id at payment recurring
                                if ($prev_subscription->recurring_active) {
                                    $recurring = $prev_subscription->recurring_active;
                                    $recurring->subscription_id = $subscription->id;
                                    $recurring->save();
                                }

                                // set inactive previous subscription
                                $prev_subscription->status = Subscription::INACTIVE;
                                $prev_subscription->save();
                            }
                        }
                    }                   
                }

                // order type subscription upgrade — tops up an existing
                // active subscription onto a higher tier rather than
                // creating a new one. The target plan was captured on
                // the order at checkout time (upgrade_to_plan_code)
                // since this webhook only has the Order row to work
                // from, not the original request.
                else if ($order->order_type == Order::SUBSCRIPTION_UPGRADE) {

                    $existing_subscription = $order->user->subscribe;
                    if ($existing_subscription && $order->upgrade_to_plan_code) {

                        // switch plan, and give a fresh quota under the
                        // new (higher) tier for the remainder of this
                        // billing cycle rather than carrying over usage
                        // counted against the old, lower-quota plan.
                        $existing_subscription->plan_code = $order->upgrade_to_plan_code;
                        $existing_subscription->orders_used_current_cycle = 0;
                        $existing_subscription->save();

                        // insert activity
                        $activity = Activity::firstOrCreate(
                            [
                                'order_id' => $order->id,
                                'user_id' => $order->user_id,
                                'transaction_id' => $transaction->id,
                                'user_type' => 'customer',
                                'title' => 'Subscription Upgrade',
                                'status' => Activity::ACTIVE
                            ],
                        );
                    }
                }

                // return success
                return response()->json([
                    'status' => true,
                    'message' => 'Payment successfully made. Thank you!',
                ]);
            }
        }
    }

    /**
     * [callback description]
     * @param  Request  $request [description]
     * @return function          [description]
     */
    public function getCallback(Request $request)
    {
        // Diagnostic log: confirms whether Fiuu's server-to-server
        // callback is actually reaching this endpoint at all. Check
        // storage/logs/laravel.log for 'Fiuu webhook hit: getCallback' if
        // payments show successful on Fiuu's own page but never flip
        // to paid in this app — an empty log here for a known test
        // means the call never arrived (check the Notification URL
        // configured in the Fiuu merchant dashboard, and that this
        // server is reachable over HTTPS from Fiuu's servers).
        Log::info('Fiuu webhook hit: getCallback', ['ip' => $request->ip(), 'body' => $request->getContent()]);

        // local environment
        if (app()->environment('local')) {
            $data = [
                'orderid' => $request->order_id,
                'status'  => '00',
                'channel' => 'FPX_MB2U',
                'extraP'   => json_encode([
                    'token'   => 'TK_2910_71512556822471214514',
                    'ccbrand' => 'Visa',
                    'cclast4' => '1111',
                    'cctype'  => 'Credit',
                ]),
            ];
            $checkPayment = true;
        }

        // server environment
        else {

            // get data request
            $content = $request->getContent();
            parse_str($content, $data);

            // verify data
            $checkPayment = $this->rms->checkVerifySignature($data);        
        }

        // check payment    
        if ($checkPayment) {

            // check data
            $order              = Order::find($data['orderid']);
            Log::info('Fiuu payment check', [
                'orderid_requested' => $data['orderid'] ?? null,
                'order_found' => $order ? true : false,
                'order_type' => $order->order_type ?? null,
                'order_status' => $order->status ?? null,
                'check_payment' => $checkPayment,
                'payment_status_code' => $data['status'] ?? null,
            ]);
            $payment            = $order->payment;
            $bag                = $order->bag;
            $order_booking      = $order->order_booking;
            $subscription       = $order->subscription;

            // status success
            if ($data['status'] == '00') {

                // insert transaction
                $transaction = Transaction::firstOrCreate(
                    ['order_id' => $order->id, 'payment_id' => $payment->id],
                    [
                        'date' => now(),
                        'type' => $order->order_type,
                        'amount' => $order->grand_total,                    
                        'status' => Transaction::PAID, 
                    ]
                );

                // update payment status
                $payment->data = json_encode($data);
                $payment->payment_method = Payment::FIUU;
                $payment->status = Payment::PAID;
                $payment->channel = $data['channel'];
                $payment->is_paid = true;
                $payment->paid_at = now();
                $payment->save();

                // update order status
                $order->status = Order::PAID;
                $order->save();

                // order type purchase bag
                if ($order->order_type == Order::PURCHASE_BAG) {

                    if ($bag) {
                        $bag->status_payment = Bag::PAID;
                        $bag->save();
                    }

                    // auto insert qrcodes
                    if ($order->quantity > 0) {
                        for ($i = 0; $i < $order->quantity; $i++) {
                            $qr = new Qrcode();
                            $code = $qr->getNextSeriesNo();
                            Qrcode::create([
                                'series_no' => $code,
                                'user_id' => $order->user_id,
                                'status' => Qrcode::SCANNED,
                                'type' => Qrcode::AUTO,
                                'scan_at' => now(),
                                'scan_by' => $order->user_id,
                                'created_by'=> $order->user_id,
                            ]);
                        }
                    }

                    // insert activity
                    $activity = Activity::firstOrCreate(
                        [
                            'order_id' => $order->id, 
                            'user_id' => $order->user_id, 
                            'transaction_id' => $transaction->id,
                            'user_type' => 'customer',
                            'title' => 'Purchase Bag', 
                            'status' => Activity::ACTIVE
                        ],
                    );

                    // send email purchase bag
                    $user = $order->user;
                    $subject = 'Auto Maid: Invoice for your purchase';
                    $emailContent = (new PurchaseBagEmail($user->name, $subject, $order))->render();
                    $onesignal = new OneSignalService();
                    $onesignal->sendEmail(
                        $user->email,
                        $subject,
                        $emailContent,
                    );
                }

                // order type booking
                else if ($order->order_type == Order::BOOKING) {

                    // insert order booking
                    $booking = Booking::firstOrCreate(
                        [
                            'order_id' => $order->id, 
                            'user_id' => $order->user_id, 
                            'pickup_location_id' => $order_booking->pickup_location_id,
                            'pickup_date' => $order_booking->pickup_date,
                        ],
                        [
                            'pickup_start_time' => $order_booking->pickup_start_time,
                            'pickup_end_time' => $order_booking->pickup_end_time,
                            'pickup_bag_quantity' => $order_booking->pickup_bag_quantity,
                            'is_folding' => $order_booking->is_folding,
                            'washing_charge' => $order_booking->washing_charge,
                            'addon_charge' => $order_booking->addon_charge,
                            'discount' => $order_booking->discount,
                            'delivery_charge' => $order_booking->delivery_charge,
                            'tax' => $order_booking->tax,
                            'grand_total' => $order_booking->grand_total,
                            'status' => Booking::ACTIVE,
                        ]
                    );

                    // update order
                    $order->booking_id = $booking->id;
                    $order->save();

                    // check voucher
                    if ($order->voucher_code) {

                        // get voucher info
                        $voucher = Voucher::where('code', $order->voucher_code)->active()->first();

                        // check taken voucher
                        $taken = $voucher->voucher_users->count();
                        if ($taken && $taken < $voucher->usage_limit) {

                            // insert voucher user
                            $voucher_user = VoucherUser::firstOrCreate(
                                [
                                    'voucher_id' => $voucher->id, 
                                    'user_id' => $order->user_id, 
                                    'order_id' => $order->id,
                                ],
                            );
                        }
                    }

                    // insert order status
                    // 01 - Waiting rider for pickup
                    // 11 - Pending for acceptance
                    // 21 - Pending for acceptance
                    $codes = [OrderStatus::CUSTOMER_WAITING_RIDER_FOR_PICKUP, OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE, OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE];
                    foreach ($codes as $key => $code) {
                        $is_customer = ($code == OrderStatus::CUSTOMER_WAITING_RIDER_FOR_PICKUP) ? 1 : 0;
                        $status = OrderStatus::firstOrCreate(
                            ['order_id' => $order->id, 'code' => $code]
                        );

                        // add assign job of customer                
                        if ($is_customer) {
                            $assign = AssignJob::firstOrCreate([
                                'order_id' => $order->id, 
                                'code' => OrderStatus::CUSTOMER_WAITING_RIDER_FOR_PICKUP,
                                'user_id' => $order->user_id,
                                'order_status_id' => $status->id,
                            ]);
                        }
                    }        

                    // insert activity
                    $activity = Activity::firstOrCreate(
                        [
                            'order_id' => $order->id, 
                            'user_id' => $order->user_id,
                            'transaction_id' => $transaction->id,                             
                            'user_type' => 'customer',
                            'title' => 'Booking', 
                            'status' => Activity::ACTIVE
                        ],
                    );

                    // get booking info
                    $booking = $order->booking;
                    $user = $order->user;

                    // send email booking                
                    $subject = 'Auto Maid: Invoice for your order';
                    $emailContent = (new NewOrderEmail($user->name, $subject, $order))->render();
                    $onesignal = new OneSignalService();
                    $onesignal->sendEmail(
                        $user->email,
                        $subject,
                        $emailContent,
                    );

                    // assign order to rider & merchant
                    Artisan::call('automaid:assign-order-to-rider-and-merchant');
                }

                // order type subscription
                else if ($order->order_type == Order::SUBSCRIPTION) {

                    // Activate the subscription as soon as THIS payment
                    // succeeds — we're already inside the
                    // `$data['status'] == '00'` (paid) branch at this
                    // point, so the customer has genuinely paid.
                    //
                    // Previously all of this (activation, bag update,
                    // qrcode issuance, activity log, email) was nested
                    // inside `if ($token) { if ($data_rec accepted) {
                    // ... } }` — gated entirely on Fiuu returning a
                    // recurring-billing token in `extraP`. Not every
                    // payment channel returns one the same way a saved
                    // credit card does (e.g. TNG eWallet) — when it
                    // didn't, this whole block silently never ran, and
                    // the subscription stayed PENDING forever even
                    // though the customer had already paid. The token
                    // is now only used for the optional recurring-token
                    // setup below, not to gate activation itself.
                    $extra = json_decode($data['extraP'] ?? 'null', true) ?? [];
                    $token = $extra['token'] ?? null;

                    // update subscription
                    $subscription->status = Subscription::ACTIVE;
                    if ($token) {
                        $subscription->cc_brand = $extra['ccbrand'] ?? null;
                        $subscription->cc_last_four = $extra['cclast4'] ?? null;
                        $subscription->cc_type = $extra['cctype'] ?? null;
                    }
                    $subscription->save();

                    // update bag
                    if ($order->bag_subscription) {
                        $bag = $order->bag_subscription;
                        $bag->status_payment = Bag::PAID;
                        $bag->save();
                    }

                    // Set up recurring billing for future auto-renewal —
                    // optional: only happens if this payment channel
                    // actually returned a token. If not, the subscription
                    // is still active for its current cycle; it just
                    // won't auto-renew via a saved token later (falls
                    // back to whatever your renewal-reminder flow does).
                    if ($token) {
                        $by_pass = 1;
                        if (app()->environment('local') || $by_pass = 1) {
                            $data_rec = [
                                [
                                    'status' => 'accepted',
                                ]
                            ];
                        } else {
                            $data_rec = $this->rms->getPaymentRequest($order, $token);
                        }

                        if ($data_rec && $data_rec[0]['status'] == 'accepted') {
                            $recurring = PaymentRecurring::firstOrCreate(
                                [
                                    'payment_id' => $payment->id, 
                                    'subscription_id' => $subscription->id, 
                                    'transaction_id' => $transaction->id,
                                    'token' => $token, 
                                    'payment_date' => $subscription->start_date,
                                    'next_payment_date' => $subscription->end_date,
                                    'data' => json_encode($data_rec), 
                                    'amount' => $order->grand_total, 
                                    'is_paid' => true, 
                                    'status' => PaymentRecurring::SUBSCRIPTION,
                                    'status_payment' => PaymentRecurring::PAID,
                                    'cc_brand' => $extra['ccbrand'] ?? null,
                                    'cc_last_four' => $extra['cclast4'] ?? null,
                                    'cc_type' => $extra['cctype'] ?? null,
                                ],
                                [
                                    'paid_at' => now()
                                ]
                            );
                        }
                    }

                    // auto insert qrcodes
                    if ($order->quantity > 0) {
                        for ($i = 0; $i < $order->quantity; $i++) {
                            $qr = new Qrcode();
                            $code = $qr->getNextSeriesNo();
                            Qrcode::create([
                                'series_no' => $code,
                                'user_id' => $order->user_id,
                                'status' => Qrcode::SCANNED,
                                'type' => Qrcode::AUTO,
                                'scan_at' => now(),
                                'scan_by' => $order->user_id,
                                'created_by'=> $order->user_id,
                            ]);
                        }
                    }

                    // insert activity
                    $activity = Activity::firstOrCreate(
                        [
                            'order_id' => $order->id, 
                            'user_id' => $order->user_id,
                            'transaction_id' => $transaction->id,
                            'user_type' => 'customer',
                            'title' => 'Subscription', 
                            'status' => Activity::ACTIVE
                        ],
                    );

                    // send email subscription
                    $user = $order->user;
                    $subject = 'Auto Maid: Your subscription purchase is successful';
                    $emailContent = (new PurchaseSubscriptionEmail($user->name, $subject, $order))->render();
                    $onesignal = new OneSignalService();
                    $onesignal->sendEmail(
                        $user->email,
                        $subject,
                        $emailContent,
                    );
                }

                // order type subscription update (update credit card)
                // no need new activity
                else if ($order->order_type == Order::SUBSCRIPTION_UPDATE) {

                    // get token
                    $extra = json_decode($data['extraP'], true);
                    $token = $extra['token'] ?? null;

                    // process recurring 
                    if ($token) {

                        // local environment
                        $by_pass = 1;                        
                        if (app()->environment('local') || $by_pass = 1) {
                            $data_rec = [
                                [
                                    'status' => 'accepted',
                                ]
                            ];
                        }

                        // server environment
                        else {

                            // process recurring
                            $data_rec = $this->rms->getPaymentRequest($order, $token);
                        }

                        // get return data
                        if ($data_rec[0]['status'] == 'accepted') {

                            // update subscription
                            $subscription->status = Subscription::ACTIVE;
                            $subscription->cc_brand = $extra['ccbrand'] ?? null; 
                            $subscription->cc_last_four = $extra['cclast4'] ?? null; 
                            $subscription->cc_type = $extra['cctype'] ?? null; 
                            $subscription->save();

                            // check previous subscription
                            $prev_subscription = Subscription::find($subscription->previous_id);
                            if ($prev_subscription) {

                                // update subscription id at bag                            
                                if ($prev_subscription->bag) {
                                    $bag = $prev_subscription->bag;
                                    $bag->subscription_id = $subscription->id;
                                    $bag->save();
                                }

                                // update subscription id at payment recurring
                                if ($prev_subscription->recurring_active) {
                                    $recurring = $prev_subscription->recurring_active;
                                    $recurring->subscription_id = $subscription->id;
                                    $recurring->save();
                                }

                                // set inactive previous subscription
                                $prev_subscription->status = Subscription::INACTIVE;
                                $prev_subscription->save();
                            }
                        }
                    }                   
                }

                // order type subscription upgrade — tops up an existing
                // active subscription onto a higher tier rather than
                // creating a new one. The target plan was captured on
                // the order at checkout time (upgrade_to_plan_code)
                // since this webhook only has the Order row to work
                // from, not the original request.
                else if ($order->order_type == Order::SUBSCRIPTION_UPGRADE) {

                    $existing_subscription = $order->user->subscribe;
                    if ($existing_subscription && $order->upgrade_to_plan_code) {

                        // switch plan, and give a fresh quota under the
                        // new (higher) tier for the remainder of this
                        // billing cycle rather than carrying over usage
                        // counted against the old, lower-quota plan.
                        $existing_subscription->plan_code = $order->upgrade_to_plan_code;
                        $existing_subscription->orders_used_current_cycle = 0;
                        $existing_subscription->save();

                        // insert activity
                        $activity = Activity::firstOrCreate(
                            [
                                'order_id' => $order->id,
                                'user_id' => $order->user_id,
                                'transaction_id' => $transaction->id,
                                'user_type' => 'customer',
                                'title' => 'Subscription Upgrade',
                                'status' => Activity::ACTIVE
                            ],
                        );
                    }
                }
            }
        }
    }

    public function getTest(Request $request)
    {
        $order = \App\Models\Order::find(1004);
        $recurring = $this->rms->getPaymentRequest2($order, 'TK_3112_83466606402246880666');
        dd($recurring);
    }

}
