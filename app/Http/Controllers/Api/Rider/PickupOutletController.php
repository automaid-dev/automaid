<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WashComplete;
use App\Models\Order;
use App\Models\AssignJob;
use App\Models\PickupOutlet;
use App\Models\OrderStatus;
use Illuminate\Support\Facades\Validator;

class PickupOutletController extends Controller
{
    /**
     * [pickupWashOutletConfirm description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function pickupWashOutletConfirm(Request $request)
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

            // check previous status
            $status_prev = AssignJob::whereIn('code', [
                    OrderStatus::RIDER_PICKUP_FROM_WASH_OUTLET, 
                    OrderStatus::MERCHANT_AWAITING_RIDER_T0_PICKUP,
                    // OrderStatus::CUSTOMER_WASH_IN_PROGRESS, // 
                ])
                ->where(['order_id' => $assign->order_id, 'is_accepted' => false])                
                ->get();
            if (count($status_prev) != 2) {            
                return response()->json([
                    'status' => false,
                    'message' => 'Already pickup from outlet.',
                ]);  
            }

            // initial merchant id
            $merchant_id = null;

            // update previous status
            foreach ($status_prev as $prev) {

                // get merchant id
                if ($prev->code == OrderStatus::MERCHANT_AWAITING_RIDER_T0_PICKUP) {
                    $merchant_id = $prev->user_id;
                }

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

            // insert pickup outlet
            $pickup = PickupOutlet::firstOrCreate([
                'order_id' => $order->id, 
                'status' => PickupOutlet::PICKUP_OUTLET,
                'created_by' => $user->id,
            ]);

            // optional photo + remark — "Delivering to customer"
            if ($request->hasFile('image') || $request->filled('remark')) {
                \App\Models\OrderStepPhoto::create([
                    'order_id' => $order->id,
                    'code' => OrderStatus::RIDER_PICKUP_FROM_WASH_OUTLET,
                    'image_path' => $request->hasFile('image') ? $request->file('image')->store('order_steps', 's3') : null,
                    'remark' => $request->remark,
                    'created_by' => $user->id,
                ]);
            }

            // insert order status
            // 04 - Delivery to customer
            // 15 - Delivery to customer
            // 25 - Rider en route to customer
            $codes = [OrderStatus::CUSTOMER_DELIVERY_TO_CUSTOMER, OrderStatus::RIDER_DELIVERY_TO_CUSTOMER, OrderStatus::MERCHANT_RIDER_EN_ROUTE_TO_CUSTOMER];
            foreach ($codes as $key => $code) {

                // set data order status
                //
                // Previously the customer branch below built $arr with
                // is_done/done_at INSIDE it and passed the whole thing
                // straight to firstOrCreate() — since done_at was set to
                // now() (a different value every call), firstOrCreate
                // could never match its own previously-created row and
                // created a fresh duplicate every time this ran. Same
                // bug, same fix as DeliveryController/PickupController/
                // BagController this round: keep the search criteria
                // (order_id + code) stable, apply is_done/done_at via
                // updateOrCreate's second argument instead so it always
                // lands on the SAME row.
                $extra = [];

                // get assign user id
                if ($code == OrderStatus::MERCHANT_RIDER_EN_ROUTE_TO_CUSTOMER) {
                    $user_id = $merchant_id;
                }
                else if ($code == OrderStatus::CUSTOMER_DELIVERY_TO_CUSTOMER) {
                    $user_id = $order->user_id;

                    // set done for customer
                    $extra = ['is_done' => true, 'done_at' => now()];
                }
                else {
                    $user_id = $user->id;
                }

                // insert order status
                $new_status = OrderStatus::updateOrCreate(['order_id' => $order->id, 'code' => $code], $extra);

                // insert assign job
                $job = AssignJob::firstOrCreate([
                    'code' => $code,
                    'user_id' => $user_id, 
                    'order_id' => $assign->order_id,
                    'order_status_id' => $new_status->id,                 
                ]);
            }

            // send pn to customer
            event(new \App\Events\CustomerDeliveryToCustomer($order->user, $job));

            // Also write to the customer app's own in-app notification
            // feed (CustomerNotification / customer_notifications table)
            // — same gap as the rider-accept step: the customer
            // notification SCREEN reads exclusively from that table
            // (Api/Customer/NotificationController), completely
            // separate from the Laravel Notifiable `notifications`
            // table used by the event above. Without this, the customer
            // only ever saw the earlier "rider is on the way to pick up"
            // notification and nothing for this later, equally
            // important step — the actual delivery to their door.
            try {
                $onesignal = new \App\Services\OneSignalService();
                $title = 'Rider is on the way';
                $message = "Rider is on the way to deliver your order {$order->id}.";
                $onesignal->notifyUser(
                    $order->user,
                    \App\Models\CustomerNotification::RIDER_ON_THE_WAY_TO_DELIVER,
                    $title,
                    $message,
                    $message,
                    $order->id,
                );
            } catch (\Throwable $th) {
                \Log::error('Failed to send customer delivery-on-the-way notification', ['error' => $th->getMessage(), 'order_id' => $order->id]);
            }

            // set data order
            $data['order'] = $order;
            return response()->json([
                'status' => true,
                'message' => 'Successfully pickup bag from outlet.',                
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
