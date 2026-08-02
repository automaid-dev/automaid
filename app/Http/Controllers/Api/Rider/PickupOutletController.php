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

            // insert order status
            // 04 - Delivery to customer
            // 15 - Delivery to customer
            // 25 - Rider en route to customer
            $codes = [OrderStatus::CUSTOMER_DELIVERY_TO_CUSTOMER, OrderStatus::RIDER_DELIVERY_TO_CUSTOMER, OrderStatus::MERCHANT_RIDER_EN_ROUTE_TO_CUSTOMER];
            foreach ($codes as $key => $code) {

                // set data order status
                $arr = ['order_id' => $order->id, 'code' => $code];

                // get assign user id
                if ($code == OrderStatus::MERCHANT_RIDER_EN_ROUTE_TO_CUSTOMER) {
                    $user_id = $merchant_id;
                }
                else if ($code == OrderStatus::CUSTOMER_DELIVERY_TO_CUSTOMER) {
                    $user_id = $order->user_id;

                    // set done for customer
                    $arr = ['order_id' => $order->id, 'code' => $code, 'is_done' => true, 'done_at' => now()];
                }
                else {
                    $user_id = $user->id;
                }

                // insert order status
                $new_status = OrderStatus::firstOrCreate($arr);

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
