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
            $setting = Setting::find(1);
            $price = $setting->subscription_price ?? 0;
            $sub_total = $request->sub_total ?? $price;

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

            // send email cancel subscription
            $subject = 'Auto Maid: Your subscription has been cancelled';
            $emailContent = (new CancelSubscriptionEmail($user->name, $subject))->render();
            $onesignal = new OneSignalService();
            $onesignal->sendEmail(
                $user->email,
                $subject,
                $emailContent,
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


