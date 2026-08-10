<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AddOn;
use App\Models\Address;
use App\Models\AssignJob;
use App\Models\Bag;
use App\Models\Booking;
use App\Models\Order;
use App\Models\OrderAddon;
use App\Models\OrderBooking;
use App\Models\OrderStatus;
use App\Models\Payment;
use App\Models\Qrcode;
use App\Models\QrcodeUser;
use App\Models\Setting;
use App\Models\State;
use App\Models\Voucher;
use App\Models\VoucherUser;
use App\Services\PaymentGateway\FiuuPaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class BookingController extends Controller
{
    /**
     * [calculateWashPrice description]
     * @param  [type] $subscription [description]
     * @param  [type] $quantity     [description]
     * @param  [type] $price        [description]
     * @param  bool   $has_quota    Whether the subscriber still has plan
     *                              order-quota remaining this cycle (see
     *                              Subscription::hasOrderQuotaRemaining()).
     *                              Ignored (treated as true) for legacy
     *                              callers that don't pass it.
     * @return [type]               [description]
     */
    public function calculateWashPrice($subscription, $quantity, $price, $total_bag_free_wash, $has_quota = true)
    {
        $total = 0;
        if ($subscription && $has_quota) {
            if ($quantity <= $total_bag_free_wash) {
                $total = 0;
            }
            else {
                $total = $price * ($quantity - $total_bag_free_wash);
            }
        }
        else {
            $total = $price * $quantity;
        }
        return $total;
    }

    /**
     * [calculateDeliveryRate description]
     * @param  [type] $quantity     [description]
     * @param  [type] $price        [description]
     * @return [type]               [description]
     *
     * Delivery is ALWAYS charged in full, regardless of subscription —
     * previously this granted free delivery for a subscriber's first
     * bag too (mirroring the free-wash benefit), but the subscription
     * benefit is wash-only. A subscriber's "free 1st bag" now means the
     * wash fee is waived; delivery for that same bag is billed at the
     * normal per-bag delivery_price from Settings, same as any
     * non-subscriber. The $subscription/$total_bag_free_delivery/
     * $has_quota parameters are kept (unused) so every existing call
     * site keeps working without needing to be touched.
     */
    public function calculateDeliveryRate($subscription, $quantity, $price, $total_bag_free_delivery, $has_quota = true)
    {
        return $price * $quantity;
    }

    /**
     * [calculateRate description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function calculateRate(Request $request)
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

            // check input
            $validate = Validator::make($request->all(), [           
                'pickup_bag_quantity' => 'required|numeric|min:1',                         
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

            // check if user have subscription
            $subscription = $user->subscribe;
            $is_subscribe = $subscription ? 1 : 0;
            $has_quota = $subscription ? $subscription->hasOrderQuotaRemaining($setting) : true;

            // get wash price
            $washing_charge = $this->calculateWashPrice($is_subscribe, $request->pickup_bag_quantity, $setting->wash_fee, $setting->total_bag_free_wash, $has_quota);

            // get delivery charge — always full price regardless of
            // subscription; the free-1st-bag benefit is wash-only.
            $delivery_charge = $this->calculateDeliveryRate($is_subscribe, $request->pickup_bag_quantity, $setting->delivery_price, $setting->total_bag_free_delivery);

            return response()->json([
                'status' => true,
                'message' => 'Successfully get delivery charge & washing charge.',
                'data' => [
                    'delivery_change' => $delivery_charge, 
                    'washing_charge' => $washing_charge
                ],
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [checkVoucher description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function checkVoucher(Request $request)
    {
        try {
            $user = auth('sanctum')->user();     
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }

            // check code
            $validate = Validator::make($request->all(), [
                'code' => 'required',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            // check voucher
            $voucher = Voucher::where('code', $request->code)->first();
            if (!$voucher) {
                return response()->json([
                    'status' => false,
                    'message' => 'Voucher not found.',
                ]);
            }

            // check voucher status
            if ($voucher->status != Voucher::ACTIVE) {
                return response()->json([
                    'status' => false,
                    'message' => 'Voucher is not active.',
                ]);
            }

            // check whether voucher already used
            $exist = Voucher::whereHas('voucher_users', function ($query) {
                $query->where('user_id', auth()->user()->id);
            })
            ->exists();
            if ($exist) {
                return response()->json([
                    'status' => false,
                    'message' => 'Voucher already used.',
                ]);
            }

            // check taken voucher
            $taken = $voucher->voucher_users->count();
            if ($taken >= $voucher->usage_limit) {
                return response()->json([
                    'status' => false,
                    'message' => 'Voucher has reach the limit.',
                ]);
            }

            // return voucher info
            $data['voucher'] = $voucher;
            return response()->json([
                'status' => true,
                'message' => 'Successfully retrieved voucher.',
                'data' => $data,
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [voucherList description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function voucherList(Request $request)
    {
        try {
            $user = auth('sanctum')->user();     
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }

            // get voucher lists
            $vouchers = Voucher::active()->latest()->get();

            // return voucher info
            $data['vouchers'] = $vouchers;
            return response()->json([
                'status' => true,
                'message' => 'Successfully retrieved voucher lists.',
                'data' => $data,
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [addOnList description]
     * @param Request $request [description]
     */
    public function addOnList(Request $request)
    {
        try {
            $user = auth('sanctum')->user();     
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }

            // get addon lists
            $addons = AddOn::active()->latest()->get();

            // return addon info
            $data['addons'] = $addons;
            return response()->json([
                'status' => true,
                'message' => 'Successfully retrieved addon lists.',
                'data' => $data,
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }


    /**
     * [getAddOn description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getAddOn(Request $request)
    {
        try {
            $user = auth('sanctum')->user();     
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }

            // get addons
            $addons = AddOn::where('status', AddOn::ACTIVE)->get();

            // return addons
            $data['addons'] = $addons;
            return response()->json([
                'status' => true,
                'message' => 'Successfully retrieved add-ons.',
                'data' => $data,
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [schedule description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function schedule(Request $request)
    {
        try {
            $user = auth('sanctum')->user();     
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }

            // check input
            $validate = Validator::make($request->all(), [
                'pickup_location_id' => 'required',              
                'pickup_date' => 'required|date',              
                'pickup_bag_quantity' => 'required|numeric|min:1',              
                'pickup_start_time' => 'required',              
                'pickup_end_time' => 'required', 
                'delivery_charge' => 'required',   
                'washing_charge' => 'required',
                'qrcodes' => 'required',   
            ], [
                'qrcodes.required' => 'Please select at least one QR code.',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            // get setting info
            // ----------------
            $setting = Setting::find(1);

            // get pickup address
            $pickup = Address::find($request->pickup_location_id);
            if (!$pickup) {
                return response()->json([
                    'status' => false,
                    'message' => 'Location is not found.',
                ]);
            }

            // get total purchased bags
            // ------------------------
            $total_purchases = (count($user->bag_purchases) > 0) ? count($user->bag_purchases) : 0;
            if ($request->pickup_bag_quantity > $total_purchases) {
                return response()->json([
                    'status' => false,
                    'message' => 'Bag is not enough.',
                ]);
            }

            // get total scanned bags
            // ----------------------
            $total_scans = (count($user->qrcodes) > 0) ? count($user->qrcodes) : 0;
            if ($request->pickup_bag_quantity > $total_scans) {
                return response()->json([
                    'status' => false,
                    'message' => 'Bag is not scan.',
                ]);
            }

            // check if pickup date already booking
            // ------------------------------------
            $chk_booking = Booking::where([
                    'user_id' => $user->id, 
                    'status' => Booking::ACTIVE
                ])
                ->whereDate('pickup_date', $request->pickup_date)
                ->first();
            if ($chk_booking) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking already made today.',
                ]);
            }

            // check if booking date is today
            // ------------------------------
            if (Carbon::parse($request->pickup_date)->format('Y-m-d') == Carbon::now()->format('Y-m-d')) {

                // get booking time
                $limit_time = Carbon::parse('10:00');
                $start_time = Carbon::parse($request->pickup_start_time);  
                
                // check if booking time more than 10 am
                if ($start_time->gt($limit_time)) { 
                    return response()->json([
                        'status' => false,
                        'message' => 'Booking for today must before 10 am.',
                    ]);
                }
            }

            // check voucher code
            // ------------------
            $voucher_code = null;
            if (isset($request->voucher_code)) {

                // check voucher
                $voucher = Voucher::where('code', $request->voucher_code)->active()->first();
                if ($voucher) {

                    // check taken voucher
                    $taken = $voucher->voucher_users->count();
                    if ($taken < $voucher->usage_limit) {

                        // set voucher code
                        $voucher_code = $request->voucher_code;
                    }
                }
            }

            // check if user have subscription
            // -------------------------------
            $subscription = $user->subscribe;
            $is_subscribe = $subscription ? 1 : 0;
            $has_quota = $subscription ? $subscription->hasOrderQuotaRemaining($setting) : true;

            // get charge — recomputed server-side, never trusted from
            // the client (a client-sent washing_charge/delivery_charge
            // here previously went straight into the order unchecked).
            $delivery_charge = $this->calculateDeliveryRate($is_subscribe, $request->pickup_bag_quantity, $setting->delivery_price, $setting->total_bag_free_delivery);
            $washing_charge = $this->calculateWashPrice($is_subscribe, $request->pickup_bag_quantity, $setting->wash_fee, $setting->total_bag_free_wash, $has_quota);
            $tax_charge = $request->tax ?? 0;
            $addon_charge = $request->addon_charge ?? 0;
            $discount = $request->discount ?? 0;

            // addon charge discount of subscription customer
            // ----------------------------------------------
            $addon_discount = 0;
            if ($request->addon_discount && $request->addon_discount > 0) {                
                $addon_discount = $request->addon_discount;
            } 

            // check if checked insurance fee
            // ------------------------------
            $insurance_fee = 0;
            if ($request->insurance_fee && $request->insurance_fee > 0) {                
                $insurance_fee = $request->insurance_fee;
            }              

            // check if checked birthday reward
            // --------------------------------
            $birthday_reward = 0;
            if ($request->birthday_reward && $request->birthday_reward > 0) {                
                $birthday_reward = $request->birthday_reward;
            }

            // get grand total
            // ---------------
            $grand_total = ($washing_charge + $delivery_charge + $addon_charge + $tax_charge + $insurance_fee) - ($discount + $addon_discount + $birthday_reward);

            // get state
            $state = State::find($pickup->state_id);

            // check if grand total is 0
            // -------------------------
            $is_zero = 0;
            if ($grand_total == 0 || $grand_total == "0.00") {
                $is_zero = 1;
            }

            // insert order
            $order = new Order();
            $order->series_no = $state ? $order->getNextSeriesNo($state->code): null;
            $order->order_type = Order::BOOKING;
            $order->user_id = $user->id;
            $order->status = ($is_subscribe && $addon_charge == 0 && $tax_charge == 0 || $is_zero) ? Order::PAID : Order::PENDING;
            $order->billing_name = $user->name ?? null;
            $order->billing_email = $user->email ?? null;
            $order->billing_phone = $user->mobile_no ?? null;
            $order->billing_address_line_1 = $pickup->address_line_1 ?? null;
            $order->billing_address_line_2 = $pickup->address_line_2 ?? null;
            $order->billing_country = $pickup->country_id ?? null;
            $order->billing_state = $pickup->state_id ?? null;
            $order->billing_postcode = $pickup->postcode ?? null;
            $order->billing_city = $pickup->city ?? null;

            $order->delivery_address_line_1 = $pickup->address_line_1 ?? null;
            $order->delivery_address_line_2 = $pickup->address_line_2 ?? null;
            $order->delivery_country = $pickup->country_id ?? null;
            $order->delivery_state = $pickup->state_id ?? null;
            $order->delivery_postcode = $pickup->postcode ?? null;
            $order->delivery_city = $pickup->city ?? null;

            $order->voucher_code = $voucher_code;
            $order->quantity = $request->pickup_bag_quantity ?? 1;
            $order->discount = $discount;
            $order->sub_total = $washing_charge;
            $order->tax_total = $tax_charge;
            $order->addon_discount = $addon_discount;
            $order->shipping_cost = $delivery_charge;

            $order->addon_discount = $addon_discount;
            $order->birthday_reward = $birthday_reward;
            $order->insurance_fee = $insurance_fee;

            $order->grand_total = $grand_total;
            $order->save();

            // check if checked birthday reward
            // --------------------------------
            if ($order->birthday_reward > 0 || $order->birthday_reward > "0.00") {

                // insert birthday reward
                \App\Models\BirthdayUser::updateOrCreate(
                    [
                        'user_id' => $user->id, 
                        'order_id' => $order->id,
                        'date' => $user->dob, 
                    ],
                    [
                        'created_by' => $user->id,
                    ]
                );
            }

            // check if checked insurance fee
            // ------------------------------
            if ($order->insurance_fee > 0 || $order->insurance_fee > "0.00") {

                // insert insurance
                \App\Models\InsuranceUser::updateOrCreate(
                    [
                        'user_id' => $user->id, 
                        'order_id' => $order->id,
                        'fee' => $order->insurance_fee,
                    ],
                    [
                        'created_by' => $user->id,                        
                    ]
                );                
            }

            // insert qrcode users
            // -------------------
            if (isset($request->qrcodes)) {
                // Accept either a JSON-encoded string (the documented
                // contract per the validation rule below) or a raw array
                // (some clients send this instead) — avoids a hard
                // TypeError from json_decode() when a client sends an
                // array directly rather than a JSON string of one.
                $qrcodes = is_array($request->qrcodes)
                    ? $request->qrcodes
                    : json_decode($request->qrcodes, true);
                $qrcodes = $qrcodes ?? [];

                if (count($qrcodes) > 0) {
                    foreach ($qrcodes as $code) {

                        // get addon detail
                        $qrcode = Qrcode::where('series_no', $code)->first();

                        // insert qrcode user
                        if ($qrcode) {
                            QrcodeUser::updateOrCreate(
                                [
                                    'order_id' => $order->id, 
                                    'qrcode_id' => $qrcode->id, 
                                ],
                                ['created_by' => auth()->user()->id]
                            );
                        }

                    }
                }
            }

            // insert order addons
            // -------------------
            if (isset($request->addons)) {
                $addons = json_decode($request->addons, true);
                if (count($addons) > 0) {
                    foreach ($addons as $i => $id) {

                        // get addon detail
                        $addon = AddOn::find($id);

                        // insert addons
                        if ($addon) {
                            OrderAddon::updateOrCreate(
                                [
                                    'order_id' => $order->id, 
                                    'user_id' => $order->user_id, 
                                    'addon_id' => $id,
                                ],
                                [ 
                                    'status' => OrderAddon::ACTIVE, 
                                    'amount' => $addon->price
                                ]
                            ); 
                        }
                    }
                }
            }

            // insert payment
            $data_payment = [
                'purchase_type' => Payment::BOOKING,
                'status' => ($is_subscribe && $addon_charge == 0 && $tax_charge == 0 || $is_zero) ? Payment::PAID : Payment::PENDING, 
                'desc' => 'Booking',
                'amount' => $order->grand_total,
            ];
            if ($is_subscribe && $addon_charge == 0 && $tax_charge == 0 || $is_zero) {
                $data_payment = array_merge($data_payment, [
                    'is_paid' => true,
                    'paid_at' => now(),
                ]);
            }
            $payment = Payment::firstOrCreate(
                ['order_id' => $order->id, 'user_id' => $user->id],
                $data_payment
            );

            // insert order booking
            $order_booking = OrderBooking::firstOrCreate(
                [
                    'order_id' => $order->id, 
                    'user_id' => $user->id, 
                    'pickup_location_id' => $request->pickup_location_id,
                    'pickup_date' => $request->pickup_date,
                ],
                [
                    'pickup_start_time' => $request->pickup_start_time ?? null,
                    'pickup_end_time' => $request->pickup_end_time ?? null,
                    'pickup_bag_quantity' => $request->pickup_bag_quantity ?? 1,
                    'is_folding' => $request->is_folding ?? 1,
                    'washing_charge' => $washing_charge ?? 0,
                    'addon_charge' => $addon_charge ?? 0,
                    'discount' => $discount ?? 0,
                    'delivery_charge' => $delivery_charge ?? 0,
                    'tax' => $tax_charge ?? 0,
                    'grand_total' => $grand_total,
                ]
            );

            // have current subscription & grand total is zero        
            if ($is_subscribe && $addon_charge == 0 && $tax_charge == 0 || $is_zero) {    

                // insert booking
                $booking = Booking::firstOrCreate(
                    [
                        'order_id' => $order->id, 
                        'user_id' => $user->id, 
                        'pickup_location_id' => $request->pickup_location_id,
                        'pickup_date' => $request->pickup_date,
                    ],
                    [
                        'pickup_start_time' => $request->pickup_start_time ?? null,
                        'pickup_end_time' => $request->pickup_end_time ?? null,
                        'pickup_bag_quantity' => $request->pickup_bag_quantity ?? 1,
                        'is_folding' => $request->is_folding ?? 1,
                        'washing_charge' => $washing_charge ?? 0,
                        'addon_charge' => $addon_charge ?? 0,
                        'discount' => $discount ?? 0,
                        'delivery_charge' => $delivery_charge ?? 0,
                        'tax' => $tax_charge ?? 0,
                        'grand_total' => $grand_total,
                        'status' => ($is_subscribe && $addon_charge == 0 && $tax_charge == 0 || $is_zero) ? Booking::ACTIVE : Booking::PENDING, 
                    ]
                );

                // update order
                $order->booking_id = $booking->id;
                $order->save();

                // count this booking against the subscription's plan order
                // quota for this cycle (bronze/silver cap; platinum &
                // legacy no-plan subscriptions are unlimited, but we still
                // track usage on them for visibility in the admin panel).
                // Without this, hasOrderQuotaRemaining() always returns
                // true and every order gets the free-wash benefit
                // regardless of the plan's actual order limit.
                if ($is_subscribe && $subscription) {
                    $subscription->orders_used_current_cycle = $subscription->orders_used_current_cycle + 1;
                    $subscription->save();
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
                            'user_id' => $user->id,
                            'order_status_id' => $status->id,
                        ]);
                    }
                }        

                // insert activity
                $activity = Activity::firstOrCreate(
                    [
                        'order_id' => $order->id, 
                        'user_id' => $user->id, 
                        'user_type' => 'customer',
                        'title' => 'Booking', 
                        'status' => Activity::ACTIVE
                    ],
                );

                // send email booking     
                $user = $order->user;           
                $subject = 'Auto Maid: Invoice for your order';
                $emailContent = (new \App\Mail\NewOrderEmail($user->name, $subject, $order))->render();
                $onesignal = new \App\Services\OneSignalService();
                $onesignal->sendEmail(
                    $user->email,
                    $subject,
                    $emailContent,
                );

                // assign order to rider & merchant
                Artisan::call('automaid:assign-order-to-rider-and-merchant');               

                $data['booking'] = $booking->load([
                    'order',
                    'user', 
                    'pickup_location',
                    'customer_order_status.status',
                ]);
                return response()->json([
                    'status' => true,
                    'message' => 'Successfully booking.',
                    'data' => $data,
                ]);
            }

            // not have subscription call payment gateway
            else {

                // call payment gateway
                $rms = new FiuuPaymentService();
                $paymentUrl = $rms->getPaymentUrl([
                    'amount' => $order->grand_total,
                    'orderid' => $order->id,
                    'bill_name' => $order->billing_name,
                    'bill_email' => $order->billing_email,
                    'bill_mobile' => $order->billing_phone,
                    'bill_desc' => 'Booking',
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
            }

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [updateLocation description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function instructions(Request $request)
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

            // check input
            $validate = Validator::make($request->all(), [
                'booking_id' => 'required',  
                'landmark_picture' => 'image|mimes:jpg,png|max:5120',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            // check booking info
            $booking = Booking::find($request->booking_id);
            if (!$booking) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found.',
                ]);  
            }

            // upload if have landmark picture
            $landmark_picture = null;
            if ($request->landmark_picture) {
                $landmark_picture = $this->uploadFile($request->landmark_picture);
            }

            // update booking landmark
            $booking->landmark = $request->landmark ?? null;
            $booking->landmark_picture = $landmark_picture;
            $booking->updated_by = $user->id;
            $booking->save();

            $data['booking'] = $booking->load([
                'order',
                'user', 
                'pickup_location',
                'customer_order_status.status',
            ]);
            return response()->json([
                'status' => true,
                'message' => 'Booking landmark success updated.',
                'data' => $data,
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [uploadFile description]
     * @param  [type] $file [description]
     * @return [type]       [description]
     */
    public function uploadFile($file)
    {
        $ext = $file->extension();
        $path = '/automaid/images/bookings/' . uniqid().date('Ymdhis') . '.' . $ext;
        $manager = new ImageManager(new Driver());
        $img = $manager->read($file);
        // $img->resize(width: 200);
        $pointer = $img->encode()->toFilePointer();
        Storage::disk('s3')->put($path, $pointer, 'public');  
        return $path;
    }

    /**
     * [qrcodeList description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function qrcodeList(Request $request)
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

            // get lists of qrcodes (scanned)
            $qrcodes = $user->scanned_qrcodes;
            if (count($qrcodes) == 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'No qrcodes found.',
                ]);  
            }

            // return all qrcodes
            $data['qrcodes'] = $qrcodes;
            return response()->json([
                'status' => true,
                'message' => 'Successfully retrieved all qrcodes.',
                'data' => $data,
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [checkBirthday description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function checkBirthday(Request $request)
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

            // check user dob
            if (!$user->dob) {
                return response()->json([
                    'status' => false,
                    'message' => 'Date of birth is not set.',
                ]);
            }

            // check if birthday reward already taken
            $alreadyTaken = \App\Models\BirthdayUser::where('user_id', $user->id)
                ->whereYear('created_at', Carbon::now()->year)
                ->exists();
                
            if ($alreadyTaken) {
                return response()->json([
                    'status' => false,
                    'is_claim' => 1,
                    'message' => 'Birthday reward already claimed.',
                ]);
            }

            // get setting birthday reward
            $setting = Setting::find(1);
            $birthday_reward_amount = $setting->birthday_reward_amount;

            $data['birthday_reward_amount'] = (float) number_format($birthday_reward_amount, 2, '.', '');
            return response()->json([
                'status' => true,
                'is_claim' => 0,
                'data' => $data,
                'message' => 'Birthday reward available.',
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [checkAddonDiscount description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function checkAddonDiscount(Request $request)
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
            $total_addon_discount = 0;

            // get setting addon discount
            $setting = Setting::find(1);
            $discount_percent = $setting->discount_percent;
            $discount_limit = $setting->discount_limit;

            // calculate addon discount
            $discount_addon_charge = $request->total_addon_charge * $discount_percent / 100;
            if ($discount_addon_charge > 0 && $discount_addon_charge <= $discount_limit) {
                $total_addon_discount = $discount_addon_charge;
            }
            else {
                $total_addon_discount = $discount_limit;
            }            

            // return total discount
            $data['total_addon_discount'] = (float) number_format($total_addon_discount, 2, '.', '');
            return response()->json([
                'status' => true,
                'data' => $data,
                'message' => 'Successfully retrieved total discount addons.',
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [checkInsurance description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function checkInsurance(Request $request)
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

            // get insurance fee
            $setting = Setting::find(1);
            $insurance_fee = $setting->insurance_fee;

            // display insurance fee
            $data['insurance_fee'] = (float) number_format($insurance_fee, 2, '.', '');
            return response()->json([
                'status' => true,
                'data' => $data,
                'message' => 'Successfully retrieved insurance fee.',
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }
}
