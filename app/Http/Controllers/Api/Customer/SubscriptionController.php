<?php

namespace App\Http\Controllers\Api\Customer;

use App\Events\CustomerCancelSubscription;
use App\Events\CustomerSubscription;
use App\Http\Controllers\Controller;
use App\Mail\CancelSubscriptionEmail;
use App\Mail\PurchaseSubscriptionEmail;
use App\Models\Activity;
use App\Models\Bag;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRecurring;
use App\Models\Setting;
use App\Models\State;
use App\Models\Subscription;
use App\Models\Unsubscribe;
use App\Models\User;
use App\Services\OneSignalService;
use App\Services\PaymentGateway\FiuuPaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    /**
     * [placeOrder description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function placeOrder(Request $request)
    {
        try {
            $user = auth('sanctum')->user();                        
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }

            // check if have current subscription
            $subscription = $user->subscribe;            
            if ($subscription) {
                return response()->json([
                    'status' => false,
                    'message' => 'Subscription already active.',
                ]);
            }

            $validate = Validator::make($request->all(), [
                'plan_code' => 'required|in:' . implode(',', Subscription::planCodes()),
                'billing_name' => 'required',              
                'billing_email' => 'required',              
                'billing_phone' => 'required',              
                'billing_address_line_1' => 'required',              
                // 'billing_address_line_2' => 'required',              
                'billing_country' => 'required',              
                'billing_state' => 'required',              
                'billing_postcode' => 'required',              
                'billing_city' => 'required',  

                'delivery_address_line_1' => 'required',              
                // 'delivery_address_line_2' => 'required',              
                'delivery_country' => 'required',              
                'delivery_state' => 'required',              
                'delivery_postcode' => 'required',              
                'delivery_city' => 'required',  
                'sub_total' => 'required',  
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            // get sub total
            // -------------
            // Price is always derived server-side from the chosen plan's
            // Settings field — never trust $request->sub_total, which was
            // only ever meant for the app to show a confirmation screen.
            // (This whole block was previously just `$sub_total =
            // $request->sub_total ?? $price;` — trusting the client
            // directly, and plan_code wasn't required/saved at all,
            // which is why every subscription's plan_code ended up NULL
            // regardless of which plan the customer actually picked.)
            $setting = Setting::find(1);
            $price = match ($request->plan_code) {
                Subscription::BRONZE => $setting->subscription_bronze_price,
                Subscription::SILVER => $setting->subscription_silver_price,
                Subscription::PLATINUM => $setting->subscription_platinum_price,
            };
            $sub_total = $price ?? 0;

            // get state
            $state = State::where('name', $request->billing_state)->first();

            // insert order
            $order = new Order();
            $order->series_no = $state ? $order->getNextSeriesNo($state->code) : null;
            $order->order_type = Order::SUBSCRIPTION;
            $order->user_id = $user->id;
            $order->status = Order::PENDING;
            $order->billing_name = $request->billing_name ?? null;
            $order->billing_email = $request->billing_email ?? null;
            $order->billing_phone = $request->billing_phone ?? null;
            $order->billing_address_line_1 = $request->billing_address_line_1 ?? null;
            $order->billing_address_line_2 = $request->billing_address_line_2 ?? null;
            $order->billing_country = $request->billing_country ?? null;
            $order->billing_state = $request->billing_state ?? null;
            $order->billing_postcode = $request->billing_postcode ?? null;
            $order->billing_city = $request->billing_city ?? null;

            $order->delivery_address_line_1 = $request->delivery_address_line_1 ?? null;
            $order->delivery_address_line_2 = $request->delivery_address_line_2 ?? null;
            $order->delivery_country = $request->delivery_country ?? null;
            $order->delivery_state = $request->delivery_state ?? null;
            $order->delivery_postcode = $request->delivery_postcode ?? null;
            $order->delivery_city = $request->delivery_city ?? null;
            $order->discount = 0;
            $order->sub_total = $sub_total;
            $order->tax_total = 0;
            $order->quantity = 1;
            $order->grand_total = $sub_total;
            $order->save();

            // insert payment
            $payment = Payment::firstOrCreate(
                ['order_id' => $order->id, 'user_id' => $user->id],
                [
                    'purchase_type' => Payment::SUBSCRIPTION,
                    'payment_method' => Payment::FIUU_RECURRING,
                    'status' => Payment::PENDING,
                    'desc' => 'Subscription',
                    'amount' => $order->grand_total,
                ]
            );

            // insert subscription
            $start = Carbon::now();
            $end = Carbon::now()->addDay();
            $subscription = Subscription::firstOrCreate(
                ['order_id' => $order->id, 'user_id' => $user->id, 'payment_id' => $payment->id],
                [
                    'start_date' => $start,
                    // 'end_date' => Carbon::now()->addMonth()->format('Y-m-d-'),
                    'end_date' => $end,
                    'start_at' => $start,
                    'renew_at' => $end,
                    'status' => Subscription::PENDING,
                    'plan_code' => $request->plan_code,
                    'start_at' => $start,
                    'renew_at' => $end,
                ]
            );

            // insert 1 free bag
            $bag = Bag::firstOrCreate(
                ['order_id' => $order->id, 'user_id' => $user->id, 'subscription_id' => $subscription->id],
                [
                    'quantity' => 1,
                    'status' => Bag::PROCESSING,
                    'status_payment' => Bag::PAID,
                    'is_subscription' => true,
                ]
            );

            // create payment
            $rms = new FiuuPaymentService();
            $paymentUrl = $rms->getPaymentUrl([
                'amount' => $order->grand_total,
                'orderid' => $order->id,
                'bill_name' => $order->billing_name,
                'bill_email' => $order->billing_email,
                'bill_mobile' => $order->billing_phone,
                'bill_desc' => 'Subscription',
                'currency' => 'MYR',
            ]);

            // return payment url (+ order_id so the app can verify
            // payment status afterwards instead of just trusting the
            // gateway redirect blindly)
            $data['url'] = $paymentUrl;
            $data['order_id'] = $order->id;
            return response()->json([
                'status' => true,
                'data' => $data,
                'message' => 'Pending on payment. Use the link to make the payment.',                
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * Upgrades an active subscription to a higher tier. The customer
     * only pays the price difference now (topup) — full new-plan price
     * takes effect from the next renewal automatically, since renewal
     * billing already reads the plan's live price from Settings via
     * Subscription::planPrice(). Downgrades aren't supported here (out
     * of scope — different UX/refund considerations).
     *
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function upgrade(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);
            }

            $subscription = $user->subscribe;
            if (!$subscription || $subscription->status != Subscription::ACTIVE) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active subscription to upgrade.',
                ]);
            }

            $validate = Validator::make($request->all(), [
                'plan_code' => 'required|in:' . implode(',', Subscription::planCodes()),
                'billing_name' => 'required',
                'billing_email' => 'required',
                'billing_phone' => 'required',
                'billing_address_line_1' => 'required',
                'billing_country' => 'required',
                'billing_state' => 'required',
                'billing_postcode' => 'required',
                'billing_city' => 'required',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            if ($subscription->plan_code === $request->plan_code) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are already on this plan.',
                ]);
            }

            // get current vs target plan price
            $setting = Setting::find(1);
            $currentPrice = $subscription->planPrice($setting);
            $newPrice = (float) match ($request->plan_code) {
                Subscription::BRONZE => $setting->subscription_bronze_price,
                Subscription::SILVER => $setting->subscription_silver_price,
                Subscription::PLATINUM => $setting->subscription_platinum_price,
                default => 0,
            };

            if ($newPrice <= $currentPrice) {
                return response()->json([
                    'status' => false,
                    'message' => 'Please choose a higher-tier plan — downgrades are not supported here.',
                ]);
            }

            // topup = flat price difference, charged now. Full new-plan
            // price applies automatically from the next renewal.
            $topup = round($newPrice - $currentPrice, 2);

            // get state
            $state = State::where('name', $request->billing_state)->first();

            // insert order for the topup amount
            $order = new Order();
            $order->series_no = $state ? $order->getNextSeriesNo($state->code) : null;
            $order->order_type = Order::SUBSCRIPTION_UPGRADE;
            $order->upgrade_to_plan_code = $request->plan_code;
            $order->user_id = $user->id;
            $order->status = Order::PENDING;
            $order->billing_name = $request->billing_name;
            $order->billing_email = $request->billing_email;
            $order->billing_phone = $request->billing_phone;
            $order->billing_address_line_1 = $request->billing_address_line_1;
            $order->billing_address_line_2 = $request->billing_address_line_2 ?? null;
            $order->billing_country = $request->billing_country;
            $order->billing_state = $request->billing_state;
            $order->billing_postcode = $request->billing_postcode;
            $order->billing_city = $request->billing_city;
            $order->discount = 0;
            $order->sub_total = $topup;
            $order->tax_total = 0;
            $order->quantity = 1;
            $order->grand_total = $topup;
            $order->save();

            // insert payment
            $payment = Payment::firstOrCreate(
                ['order_id' => $order->id, 'user_id' => $user->id],
                [
                    'purchase_type' => Payment::SUBSCRIPTION,
                    'payment_method' => Payment::FIUU,
                    'status' => Payment::PENDING,
                    'desc' => 'Subscription upgrade to ' . ucfirst($request->plan_code),
                    'amount' => $order->grand_total,
                ]
            );

            // create payment
            $rms = new FiuuPaymentService();
            $paymentUrl = $rms->getPaymentUrl([
                'amount' => $order->grand_total,
                'orderid' => $order->id,
                'bill_name' => $order->billing_name,
                'bill_email' => $order->billing_email,
                'bill_mobile' => $order->billing_phone,
                'bill_desc' => 'Subscription Upgrade',
                'currency' => 'MYR',
            ]);

            $data['url'] = $paymentUrl;
            $data['order_id'] = $order->id;
            $data['topup_amount'] = $topup;
            return response()->json([
                'status' => true,
                'data' => $data,
                'message' => 'Pending on payment. Use the link to make the payment.',
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * [change credit card info]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function updateSubscription(Request $request)
    {
        try {
            $user = auth('sanctum')->user();            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }

            // check current subscription
            $subscription = $user->subscribe;            
            if (!$subscription) {
                return response()->json([
                    'status' => false,
                    'message' => 'No Subscription/Expired.',
                ]);
            }

            // get sub total 
            $sub_total = 1; // RM1

            // get state
            $state = ($user->state_id) ? State::find($user->state_id) : null;
            $d_state = ($user->d_state_id) ? State::find($user->d_state_id) : null;

            // insert order
            $order = new Order();
            $order->series_no = $state ? $order->getNextSeriesNo($state->code) : null;
            $order->order_type = Order::SUBSCRIPTION_UPDATE;
            $order->user_id = $user->id;
            $order->status = Order::PENDING;
            $order->billing_name = $user->name ?? null;
            $order->billing_email = $user->email ?? null;
            $order->billing_phone = $user->phone_no ?? null;
            $order->billing_address_line_1 = $user->address_line_1 ?? null;
            $order->billing_address_line_2 = $user->address_line_2 ?? null;
            $order->billing_country = $user->country_id ?? null;
            $order->billing_state = ($state) ? $state->name : null;
            $order->billing_postcode = $user->postcode ?? null;
            $order->billing_city = $user->city ?? null;

            $order->delivery_address_line_1 = $user->d_address_line_1 ?? null;
            $order->delivery_address_line_2 = $user->d_address_line_2 ?? null;
            $order->delivery_country = $user->d_country_id ?? null;
            $order->delivery_state = ($d_state) ? $d_state->name : null;
            $order->delivery_postcode = $user->d_postcode ?? null;
            $order->delivery_city = $user->d_city ?? null;
            $order->discount = 0;
            $order->sub_total = $sub_total;
            $order->tax_total = 0;
            $order->quantity = 1;
            $order->grand_total = $sub_total;
            $order->save();

            // insert payment
            $payment = Payment::firstOrCreate(
                ['order_id' => $order->id, 'user_id' => $user->id],
                [
                    'purchase_type' => Payment::SUBSCRIPTION,
                    'payment_method' => Payment::FIUU_RECURRING,
                    'status' => Payment::PENDING,
                    'desc' => 'Subscription Renewal',
                    'amount' => $order->grand_total,
                ]
            );

            // insert new subscription
            $new_subscription = Subscription::firstOrCreate(
                ['order_id' => $order->id, 'user_id' => $user->id, 'payment_id' => $payment->id],
                [
                    'start_date' => $subscription->start_date,
                    'end_date' => $subscription->end_date,
                    'status' => Subscription::PENDING,
                    'plan_code' => $subscription->plan_code,
                    'start_at' => $subscription->start_at,
                    'renew_at' => $subscription->renew_at,
                    'previous_id' => $subscription->id,
                ]
            );

            // create payment
            $rms = new FiuuPaymentService();
            $paymentUrl = $rms->getPaymentUrl([
                'amount' => $order->grand_total,
                'orderid' => $order->id,
                'bill_name' => $order->billing_name,
                'bill_email' => $order->billing_email,
                'bill_mobile' => $order->billing_phone,
                'bill_desc' => 'Subscription Renewal',
                'currency' => 'MYR',
            ]);

            // return payment url (+ order_id so the app can verify
            // payment status afterwards instead of just trusting the
            // gateway redirect blindly)
            $data['url'] = $paymentUrl;
            $data['order_id'] = $order->id;
            return response()->json([
                'status' => true,
                'data' => $data,
                'message' => 'Pending on payment. Use the link to make the payment.',                
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [cancelSubscription description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    /**
     * Lists every subscription-related order for the logged-in customer
     * (initial subscribe, renewals, card updates, upgrades) — used for
     * the app's Subscription History screen. Each entry has enough to
     * show in a list; tapping one re-fetches full detail via the
     * existing generic OrderController::orderDetail (same one already
     * used for bag purchase / booking receipts) to build a receipt.
     *
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function history(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);
            }

            $orders = Order::where('user_id', $user->id)
                ->whereIn('order_type', [
                    Order::SUBSCRIPTION,
                    Order::SUBSCRIPTION_RENEWAL,
                    Order::SUBSCRIPTION_UPDATE,
                    Order::SUBSCRIPTION_UPGRADE,
                ])
                ->orderByDesc('id')
                ->get();

            return response()->json([
                'status' => true,
                'data' => ['orders' => $orders],
                'message' => 'Successfully retrieved subscription history.',
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * [cancelSubscription description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function cancelSubscription(Request $request)
    {
        try {
            $user = auth('sanctum')->user();            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }

            // get subscription info
            $subscription = $user->subscribe;
            if (!$subscription) {
                return response()->json([
                    'status' => false,
                    'message' => 'Subscription not found.',
                ]);  
            }

            // check if subscription already cancelled
            if ($subscription->status == Subscription::CANCELLED) {
                return response()->json([
                    'status' => false,
                    'message' => 'Subscription already cancelled.',
                ]);  
            }

            // cancel bag subscription
            if ($subscription->bag) {
                $subscription->bag->status = Bag::CANCELLED;
                $subscription->bag->updated_by = auth()->user()->id;
                $subscription->bag->save();
            }

            // cancel payment recurring
            if ($subscription->recurring_active) {
                $subscription->recurring_active->status = PaymentRecurring::CANCELLED;
                $subscription->recurring_active->updated_by = auth()->user()->id;
                $subscription->recurring_active->save();
            }

            // insert unsubscribe
            $unsubscribe = Unsubscribe::firstOrCreate(
                [
                    'user_id' => auth()->user()->id,
                    'subscription_id' => $subscription->id, 
                    'order_id' => $subscription->order_id, 
                ],
                [
                    'amount' => $subscription->order->grand_total,
                    'status' => 'unsubscribe',
                ]
            );

            // insert activity
            $activity = Activity::firstOrCreate(
                [
                    'order_id' => $subscription->order_id, 
                    'user_id' => auth()->user()->id,                    
                    'user_type' => 'customer',
                    'title' => 'Cancel Subscription', 
                    'status' => Activity::ACTIVE
                ],
            );

            // update subscription status
            $subscription->status = Subscription::CANCELLED;
            $subscription->updated_by = $user->id;
            $subscription->save();

            // send email + in-app notification + push (subscription cancelled)
            $subject = 'Auto Maid: Your subscription has been cancelled';
            $emailContent = (new CancelSubscriptionEmail($user->name, $subject))->render();
            $onesignal = new OneSignalService();
            $onesignal->notifyUser(
                $user,
                \App\Models\Notification::SUBSCRIPTION_CANCELLED,
                $subject,
                'Your subscription has been cancelled.',
                $emailContent,
                $subscription->order_id,
            );

            // return data cancel subscription
            $data['unsubscribe'] = $unsubscribe->load(['subscription', 'order']);
            return response()->json([
                'status' => true,
                'message' => 'Successfully cancel the subscription.',
                'data' => $data,
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }


}


