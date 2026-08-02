<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Bag;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\State;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Qrcode;
use App\Models\Transaction;
use App\Services\OneSignalService;
use App\Services\PaymentGateway\FiuuPaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderBagController extends Controller
{
    /**
     * [placeOrder description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function placeOrder(Request $request)
    {        
        try {
            // check user
            $user = auth('sanctum')->user();            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }

            // check required fields            
            $validate = Validator::make($request->all(), [
                'billing_name' => 'required',              
                'billing_email' => 'required',              
                'billing_phone' => 'required',              
                'billing_address_line_1' => 'required',              
                'billing_country' => 'required',              
                'billing_state' => 'required',              
                'billing_postcode' => 'required',              
                'billing_city' => 'required',  

                'delivery_address_line_1' => 'required',              
                'delivery_country' => 'required',              
                'delivery_state' => 'required',              
                'delivery_postcode' => 'required',              
                'delivery_city' => 'required',  

                'sub_total' => 'required',  
                'quantity' => 'required',  
                'grand_total' => 'required',  
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            // get setting info
            $setting = Setting::find(1);
            $state = State::where('name', $request->billing_state)->first();

            // get total
            $sub_total = $request->sub_total;
            $quantity = $request->quantity;
            $grand_total = $request->grand_total;

            // insert order
            $order = new Order();
            $order->series_no = $state ? $order->getNextSeriesNo($state->code) : null;
            $order->order_type = Order::PURCHASE_BAG;
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
            $order->quantity = $quantity;
            $order->grand_total = $grand_total;
            $order->save();

            // by pass payment gateway (claim free bag)
            if ($quantity == 1 && $grand_total == 0 || $grand_total == "0.00") {

                // insert bag
                Bag::firstOrCreate(
                    ['order_id' => $order->id, 'user_id' => $user->id],
                    [
                        'status' => Bag::PROCESSING, 
                        'status_payment' => Bag::PAID,
                        'quantity' => 1,
                        'is_free' => true,
                    ]
                );

                // insert qrcode
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

                // insert payment
                $payment = Payment::firstOrCreate(
                    ['order_id' => $order->id, 'user_id' => $user->id],
                    [
                        'purchase_type' => Payment::PURCHASE_BAG,
                        'status' => Payment::PAID, 
                        'desc' => 'Purchase Bag',
                        'amount' => 0,
                        'is_paid' => true,
                        'paid_at' => now(),
                    ]
                );

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

                // update order status
                $order->status = Order::PAID;
                $order->save();

                // insert activity
                Activity::firstOrCreate(
                    [
                        'order_id' => $order->id, 
                        'user_id' => $order->user_id, 
                        'transaction_id' => $transaction->id,
                        'user_type' => 'customer',
                        'title' => 'Claim Bag Free', 
                        'status' => Activity::ACTIVE
                    ],
                );

                // return data
                $data['order'] = $order->load([
                    'user', 
                    'payment',
                    'bag_purchases',
                ]);
                return response()->json([
                    'status' => true,
                    'message' => 'Successfully claim free bag.',
                    'data' => $data,
                ]);
            }

            // normal buy bag
            else {

                // insert payment
                $payment = Payment::firstOrCreate(
                    ['order_id' => $order->id, 'user_id' => $user->id],
                    [
                        'purchase_type' => Payment::PURCHASE_BAG,
                        'status' => Payment::PENDING, 
                        'desc' => 'Purchase Bag',
                        'amount' => $order->grand_total,
                    ]
                );

                // insert bag
                $bag = Bag::firstOrCreate(
                    ['order_id' => $order->id, 'user_id' => $user->id],
                    [
                        'status' => Bag::PROCESSING, 
                        'status_payment' => Bag::PENDING,
                        'quantity' => $quantity,
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
                    'bill_desc' => 'Purchase Bag',
                    'currency' => 'MYR',
                ]);

                // return payment url
                $data['url'] = $paymentUrl;
                return response()->json([
                    'status' => true,
                    'data' => $data,
                    'message' => 'Pending on payment. Use the link to make the payment.',                
                ], 200);
            }

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }




}
