<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Models\AssignJob;
use App\Models\Bag;
use App\Models\Order;
use App\Models\OrderPickup;
use App\Models\OrderStatus;
use App\Models\Qrcode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * [acceptOrder description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function acceptOrder(Request $request)
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

            // get order status
            $status = $assign->order_status;
            if (!$status) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order status not found.',
                ]);  
            }

            // check assign job
            $accepted = AssignJob::where('id', $request->assign_id)->where(['is_accepted' => true])->first();
            if ($accepted) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job already accepted.',
                ]);  
            }

            // check if other user have accepted the job
            $taken = AssignJob::where(['code' => OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE, 'order_id' => $assign->order_id, 'is_accepted' => true])->first();
            if ($taken) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job already taken.',
                ]);  
            }

            // check previous status customer
            $prev = AssignJob::whereIn('code', [OrderStatus::CUSTOMER_WAITING_RIDER_FOR_PICKUP])
                ->where(['order_id' => $assign->order_id, 'is_accepted' => false])
                ->first();
            if ($prev) {

                // update assign job
                $prev->is_accepted = true;
                $prev->accepted_at = now();
                $prev->accepted_by = $user->id;
                $prev->save();

                // update order status
                $prev->order_status->is_done = true;
                $prev->order_status->done_at = now();
                $prev->order_status->save();
            }

            // update status accepted (11)
            $assign->is_accepted = true;
            $assign->accepted_at = now();
            $assign->accepted_by = $user->id;
            $assign->save();

            // update order status (11)
            $status->is_done = true;
            $status->done_at = now();
            $status->updated_by = auth()->user()->id;
            $status->save();

            // insert order status
            // 12 - Ready for Pickup
            // 02 - delivery to wash outlet   
            $customer_job = null;
            $rider_job = null;
            $codes = [OrderStatus::CUSTOMER_DELIVERY_TO_WASH_OUTLET, OrderStatus::RIDER_READY_FOR_PICKUP];
            foreach ($codes as $key => $code) {                     
                $new_status = OrderStatus::firstOrCreate(
                    [
                        'order_id' => $assign->order_id, 
                        'code' => $code,
                    ]
                );

                // insert assign job
                $job = AssignJob::firstOrCreate([
                    'code' => $code,
                    'user_id' => ($code == OrderStatus::CUSTOMER_DELIVERY_TO_WASH_OUTLET) ? $order->user_id : $user->id,                     
                    'order_id' => $assign->order_id,
                    'order_status_id' => $new_status->id,             
                ]);
                if ($code == OrderStatus::CUSTOMER_DELIVERY_TO_WASH_OUTLET) {
                    $customer_job = $job;
                }
                else {
                    $rider_job = $job;
                }
            }

            // set previous assignee queue false
            AssignJob::where([
                'code' => OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE,
                'order_id' => $assign->order_id,
            ])->whereNotIn('id', [$assign->id])
            ->update(['is_queue' => false]);

            // send pn to customer
            event(new \App\Events\CustomerReadyPickup($order->user, $customer_job));

            // Also write to the customer app's own in-app notification
            // feed (CustomerNotification / customer_notifications table)
            // — the customer notification SCREEN reads exclusively from
            // that table (see Api/Customer/NotificationController),
            // completely separate from the Laravel Notifiable
            // `notifications` table used above by CustomerReadyPickup.
            // Without this, CustomerReadyPickup's email/push still went
            // out, but nothing ever showed up on the customer's actual
            // in-app notification list.
            try {
                $onesignal = new \App\Services\OneSignalService();
                $title = 'Rider is on the way';
                $message = "Rider accepted your order {$order->id} and on the way to pick up your laundry item.";
                $onesignal->notifyUser(
                    $order->user,
                    \App\Models\CustomerNotification::RIDER_ACCEPTED,
                    $title,
                    $message,
                    $message,
                    $order->id,
                );
            } catch (\Throwable $th) {
                \Log::error('Failed to send customer rider-accepted notification', ['error' => $th->getMessage(), 'order_id' => $order->id]);
            }

            // notify the merchant this order is coming their way — a
            // pure heads-up, not tied to their own job's status, so it
            // doesn't touch $order->merchant / their AssignJob at all.
            $merchantPendingJob = AssignJob::where([
                'code' => OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE,
                'order_id' => $assign->order_id,
            ])->first();
            if ($merchantPendingJob && $merchantPendingJob->user) {
                event(new \App\Events\MerchantRiderOnTheWay($merchantPendingJob->user, $merchantPendingJob));
            }

            // return data after accept order
            $data['assign'] = $assign->load([
                'user.rider',
                'booking.rider_order_status.status', 
            ]);
            return response()->json([
                'status' => true,
                'message' => 'Rider successfully accept order.',
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
     * [listQrcodes description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function listQrcodes(Request $request)
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
                'user_id' => 'required',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            // get lists of qrcodes
            $qrcodes = Qrcode::where(['user_id' => $request->user_id])->get();
            if (count($qrcodes) == 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Qcode not found.',
                ]);  
            }

            // return data qrcodes
            $data['qrcodes'] = $qrcodes;
            return response()->json([
                'data' => $data,
                'status' => true,
                'message' => 'Qrcodes successfully retrieved.',
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [orderDetail description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function orderDetail(Request $request)
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
                'id' => 'required',
                'is_complete' => 'required',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }
            
            // check by order_id
            if ($request->is_complete) {

                // check order data
                $order = Order::where('id', $request->id)->first();
                if (!$order) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Order not found.',
                    ]); 
                }

                // return data order details
                $order->load([
                    'booking.pickup_location', 
                    'rider_order_statuses.status', 
                    'merchant.user.merchant.outlet',
                    'order_addons.addon',
                    'delivered',
                    'qrcode_users.qrcode',
                    // Scoped to this rider's own Commission so the app
                    // can show a "Settled"/"Pending" badge on this
                    // order — same scoping as ProfileController's
                    // activity feed, never exposing another party's cut.
                    'commission_transactions' => function ($q) use ($user) {
                        $q->whereHas('commission', function ($cq) use ($user) {
                            $cq->where('user_id', $user->id);
                        });
                    },
                ]);
                $data['order'] = $order;
                return response()->json([
                    'data' => $data,
                    'status' => true,
                    'message' => 'Order details successfully retrieved.',
                ]);
            }

            // check by assign_id
            else {

                // check assign data
                $assign = AssignJob::where('id', $request->id)->where('user_id', $user->id)->first();
                if (!$assign) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Order not found.',
                    ]); 
                }

                // return data order details
                $assign->load([
                    'user.rider', 
                    'order.booking.pickup_location', 
                    'order.rider_order_statuses.status', 
                    'order.merchant.user.merchant.outlet',
                    'order.order_addons.addon', 
                    'order.qrcode_users.qrcode', 
                ]);
                $data['assign_job'] = $assign;
                return response()->json([
                    'data' => $data,
                    'status' => true,
                    'message' => 'Order details successfully retrieved.',
                ]);
            }

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }


}
