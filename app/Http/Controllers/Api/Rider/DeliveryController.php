<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AssignJob;
use App\Models\Booking;
use App\Models\Commission;
use App\Models\CommissionTransaction;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\OrderComplete;
use App\Models\OrderStatus;
use App\Models\PickupOutlet;
use App\Models\Rider;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class DeliveryController extends Controller
{
	/**
	 * [deliveryConfirm description]
	 * @param  Request $request [description]
	 * @return [type]           [description]
	 */
	public function deliveryConfirm(Request $request)
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
                'assign_id' => 'required',
                'image' => 'required|image|max:10240', // 10MB — a photo is required to confirm delivery, matching every other handoff step
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            // check existing job
            $assign = AssignJob::find($request->assign_id);
            if (!$assign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not exist.',
                ]);  
            }

            // get order info
            $order = $assign->order;
            if (!$order) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order not found.',
                ]);  
            }

            // get merchant info
            $merchant = $order->merchant->accepted_user;
            if (!$merchant) {
                return response()->json([
                    'status' => false,
                    'message' => 'Merchant not found.',
                ]);  
            }

            // get order status
            $status = $assign->order_status;
            if (!$status) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order status not found.',
                ]);  
            }

            // get booking info
            $booking = $order->booking;
            if (!$booking) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found.',
                ]);  
            }

            // check previous status
            $status_prev = AssignJob::whereIn('code', [
                    OrderStatus::CUSTOMER_DELIVERY_TO_CUSTOMER, 
                    OrderStatus::RIDER_DELIVERY_TO_CUSTOMER, 
                    OrderStatus::MERCHANT_RIDER_EN_ROUTE_TO_CUSTOMER
                ])            
                ->where(['order_id' => $order->id, 'is_accepted' => false])
                ->get();
            if (count($status_prev) != 3) {                        
                return response()->json([
                    'status' => false,
                    'message' => 'Order not pickup from rider yet.',
                ]);  
            }

            // update previous status
            foreach ($status_prev as $prev) {

                // update status assign if not accept yet
                $prev->is_accepted = true;
                $prev->accepted_at = now();
                $prev->accepted_by = $user->id;            
                $prev->save();

                // update order status
                $prev->order_status->is_done = true;
                $prev->order_status->done_at = now();
                $prev->order_status->save();
            }

            // insert order complete
            $complete = OrderComplete::firstOrCreate([
                'order_id' => $order->id, 
                'status' => OrderComplete::DELIVERED,
                'created_by' => $user->id,
            ]);

            // Photo is required (validated above) — stored the same way
            // as every other handoff step's photo, via OrderStepPhoto,
            // rather than OrderComplete's own unused image1/2/3 columns,
            // so all step photos live in one consistent place.
            \App\Models\OrderStepPhoto::create([
                'order_id' => $order->id,
                'code' => OrderStatus::RIDER_ORDER_DELIVERED,
                'image_path' => $request->file('image')->store('order_steps', 's3'),
                'remark' => $request->remark,
                'created_by' => $user->id,
            ]);

	        // insert order status
	        // 05 - Order delivered
	        // 16 - Order delivered
	        // 26 - Order delivered
            $merchant_job = null;
            $customer_job = null; 
	        $codes = [OrderStatus::CUSTOMER_ORDER_DELIVERED, OrderStatus::RIDER_ORDER_DELIVERED, OrderStatus::MERCHANT_ORDER_DELIVERED];
	        foreach ($codes as $key => $code) {

                // get assign user id
                if ($code == OrderStatus::MERCHANT_ORDER_DELIVERED) {
                    $user_id = $merchant->id;
                }
                else if ($code == OrderStatus::CUSTOMER_ORDER_DELIVERED) {
                    $user_id = $order->user_id;
                }
                else {
                    $user_id = $user->id;
                }

                // insert order status
                // Previously this passed is_done/done_at INSIDE the
                // search criteria, with done_at set to now() — a value
                // that's different on every single call. That meant
                // firstOrCreate() could never find its own previously-
                // created row (the search would never match a past
                // timestamp), so every call created a brand new
                // duplicate OrderStatus row for the same order+code,
                // which in turn made the AssignJob::firstOrCreate()
                // below it duplicate too (its own search includes this
                // row's id). updateOrCreate matches on the stable
                // identity fields only, then always applies is_done/
                // done_at on top — whether the row is new or already
                // existed.
                $new_status = OrderStatus::updateOrCreate(
                    ['order_id' => $order->id, 'code' => $code],
                    ['is_done' => true, 'done_at' => now()]
                );

                // insert assign job
                $job = AssignJob::firstOrCreate([
                    'code' => $code,
                    'user_id' => $user_id, 
                    'order_id' => $assign->order_id,
                    'order_status_id' => $new_status->id,
                    'is_accepted' => true,
                    'accepted_at' => now(),
                    'accepted_by' => $user->id,                
                ]);
                if ($code == OrderStatus::CUSTOMER_ORDER_DELIVERED) {
                    $customer_job = $job;
                }
                if ($code == OrderStatus::MERCHANT_ORDER_DELIVERED) {
                    $merchant_job = $job;
                }
	        }

            // update status booking
            $booking->status = Booking::CUSTOMER;
            $booking->updated_by = $user->id;
            $booking->save();

            // get total without sst
            $delivery_charge = $booking->delivery_charge ?? 0;
            $washing_charge = $booking->washing_charge ?? 0;
            $addon_charge = $booking->addon_charge ?? 0;
            $discount = $booking->discount ?? 0;
            $grand_total = ($washing_charge + $delivery_charge + $addon_charge) - $discount;

            // set commission rider
            $commission = $this->getTotalCommission(User::RIDER, $user->rider->type_rider, $grand_total);
            if ($commission > 0) {

                // insert commission rider
                $this->insertCommissionEwallet($commission, $user->id, $order->id);
            }

            // set commission merchant
            $commission = $this->getTotalCommission(User::MERCHANT, $merchant->merchant->type_merchant, $grand_total);
            if ($commission > 0) {

                // insert commission merchant
                $this->insertCommissionEwallet($commission, $merchant->id, $order->id);
            }

            // insert activity rider & merchant
            $user_types = [
                ['rider', $user->id], 
                ['merchant', $merchant->id],
            ];
            foreach ($user_types as $type) {

                // check activity
                $activity = Activity::where([
                    'order_id' => $order->id, 
                    'user_id' => $type[1], 
                    'user_type' => $type[0],
                ])->first();
                if (!$activity) {
                    $data = new Activity();
                    $data->order_id = $order->id;
                    $data->user_id = $type[1];
                    $data->user_type = $type[0];
                    $data->title = 'Order Delivered';
                    $data->status = Activity::ACTIVE;
                    $data->save();
                }
            }                

            // send email order completed to customer             
            $subject = 'Auto Maid: Done, Your Order Today has been Delivered!';
            $customer = $order->user;
            $emailContent = (new \App\Mail\OrderCompletedEmail($customer->name, $subject, $order))->render();
            $onesignal = new \App\Services\OneSignalService();
            $onesignal->sendEmail(
                $customer->email,
                $subject,
                $emailContent,
            );

            // send pn to merchant
            event(new \App\Events\MerchantOrderDelivered($merchant, $merchant_job));

            // send pn to customer
            event(new \App\Events\CustomerOrderDelivered($order->user, $customer_job));

            // Also write to the customer app's own in-app notification
            // feed (CustomerNotification / customer_notifications table)
            // — same gap as the two earlier rider steps this
            // conversation: the customer notification SCREEN reads
            // exclusively from that table (Api/Customer/
            // NotificationController), completely separate from the
            // Laravel Notifiable `notifications` table used by the
            // event above and the completion email already being sent.
            // Without this, the customer's in-app notification list
            // stayed frozen at "rider is on the way" for the rest of
            // the order's life, even though it had actually finished.
            try {
                $onesignal2 = new \App\Services\OneSignalService();
                $title = 'Order delivered';
                $message = "Rider has delivered your order {$order->id}. Your order is now completed — thank you for using AutoMaid!";
                $onesignal2->notifyUser(
                    $order->user,
                    \App\Models\CustomerNotification::ORDER_DELIVERED,
                    $title,
                    $message,
                    $message,
                    $order->id,
                );
            } catch (\Throwable $th) {
                \Log::error('Failed to send customer order-delivered notification', ['error' => $th->getMessage(), 'order_id' => $order->id]);
            }

	        $data['assign'] = $assign;
	        return response()->json([
	            'status' => true,
	            'message' => 'Successfully delivered.',                
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
     * [insertCommissionEwallet description]
     * @param  [type] $amount   [description]
     * @param  [type] $user_id  [description]
     * @param  [type] $order_id [description]
     * @return [type]           [description]
     */
    public function insertCommissionEwallet($amount, $user_id, $order_id)
    {
        // check commission
        $commission = Commission::where('user_id', $user_id)->first();
        if (!$commission) {
            $commission = new Commission();
            $commission->user_id = $user_id;
            $commission->balance = $amount;
            $commission->status = Commission::PENDING;
            $commission->last_transaction_at = now();
            $commission->save();
        }

        // add transaction
        CommissionTransaction::firstOrCreate(
            [
                'commission_id' => $commission->id, 
                'order_id' => $order_id,
            ],
            [
                'type' => CommissionTransaction::EARNED,
                'amount' => $commission->balance,
                'final_amount' => $commission->balance,
                'status' => CommissionTransaction::PENDING,
                'desc' => null,
            ]
        );

        // update balance
        $total = $commission->transactions()->sum('amount'); 
        $commission->balance = $total;
        $commission->last_transaction_at = now();
        $commission->save();        
        return true;
    }

    /**
     * [getTotalCommission description]
     * @param  [type] $role  [description]
     * @param  [type] $type  [description]
     * @param  [type] $total [description]
     * @return [type]        [description]
     */
    public function getTotalCommission($role, $type, $total)
    {
        $commission = 0;

        // get setting
        $setting = Setting::find(1);

        // role rider
        if ($role == User::RIDER) {

            // get rider commission
            $limitCommission = $setting->rider_commission; // 10
            $minCommission = $setting->rider_minimum_commission; // 100

            // gig worker
            if ($type == Rider::TYPE_GIG_WORKER) {
                $commissionRate = $setting->rider_gig_worker_commission; // 15%
            }

            // staff auto maid
            else {
                $commissionRate = $setting->rider_staff_automaid_commission;                
            }
        }

        // role merchant
        else {

            // get merchant commission
            $limitCommission = $setting->merchant_commission;
            $minCommission = $setting->merchant_minimum_commission;

            // outlet partner
            if ($type == Merchant::TYPE_OUTLET_PARTNER) {
                $commissionRate = $setting->merchant_outlet_partner_commission;
            }

            // auto maid outlet
            else {
                $commissionRate = $setting->merchant_automaid_outlet_commission;                
            }
        }

        // calculate commission
        $commission = $total * ($commissionRate / $limitCommission); // 30 * (15 / 10)

        // check conditions
        if ($total == 0 || $commission < $minCommission) {
            $commission = $minCommission;
        }
        return $commission;
    }

    /**
     * [deliveryUpload description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function deliveryUpload(Request $request)
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
                'assign_id' => 'required',
                'image1' => 'image|mimes:jpg,png|max:10240',
                'image2' => 'image|mimes:jpg,png|max:10240',
                'image2' => 'image|mimes:jpg,png|max:10240',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            // check existing job
            $assign = AssignJob::find($request->assign_id);
            if (!$assign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not exist.',
                ]);  
            }

            // get order info
            $order = $assign->order;
            if (!$order) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order not found.',
                ]);  
            }

            // check order delivered
            $complete = OrderComplete::where([
                'order_id' => $order->id, 
                'status' => OrderComplete::DELIVERED,
                'created_by' => $user->id,
            ])
            ->first();
            if (!$complete) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order delivered not found.',
                ]);  
            }

            $image1 = null;
            if ($request->image1) {
                $image1 = $this->uploadFile($request->image1);
            }

            $image2 = null;
            if ($request->image2) {
                $image2 = $this->uploadFile($request->image2);
            }

            $image3 = null;
            if ($request->image3) {
                $image3 = $this->uploadFile($request->image3);
            }

            // update upload image
            $complete->image1 = $image1;
            $complete->image2 = $image2;
            $complete->image3 = $image3;
            $complete->save();

            $order->load(['delivered']);
            $data['order'] = $order;
            return response()->json([
                'status' => true,
                'message' => 'Successfully upload image.',                
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
        $path = '/automaid/images/orders/delivery/uploads/' . uniqid().date('Ymdhis') . '.' . $ext;
        $manager = new ImageManager(new Driver());
        $img = $manager->read($file);
        // $img->resize(width: 200);
        $pointer = $img->encode()->toFilePointer();
        Storage::disk('s3')->put($path, $pointer, 'public');  
        return $path;
    }
}
