<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Models\AssignJob;
use App\Models\Bag;
use App\Models\BagReceive;
use App\Models\BagScan;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Booking;
use App\Models\OrderPickup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BagController extends Controller
{
    /**
     * [bagReceive description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function bagReceive(Request $request)
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

            // get booking info
            $booking = $order->booking;
            if (!$booking) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found.',
                ]);  
            }

            // check previous status
            $status_prev = AssignJob::whereIn('code', [OrderStatus::CUSTOMER_DELIVERY_TO_WASH_OUTLET, OrderStatus::RIDER_DELIVERY_TO_WASH_OUTLET, OrderStatus::MERCHANT_AWAITING_BAG_DELIVERY])
                ->where(['order_id' => $order->id, 'is_accepted' => false])            
                ->get();
            if (count($status_prev) != 3) {                        
                return response()->json([
                    'status' => false,
                    'message' => 'Rider not pickup yet.',
                ]);  
            }

            // initial rider id
            $rider_id = null;

            // update previous status
            foreach ($status_prev as $prev) {

                // get rider id
                if ($prev->code == OrderStatus::RIDER_DELIVERY_TO_WASH_OUTLET) {
                    $rider_id = $prev->user_id;
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

            // insert bag receive
            $receive = BagReceive::firstOrCreate([
                'order_id' => $order->id, 
                'status' => BagReceive::WASH_IN_PROGRESS,
                'created_by' => $user->id,
            ]);

            // optional photo + remark — "Wash in progress"
            if ($request->hasFile('image') || $request->filled('remark')) {
                \App\Models\OrderStepPhoto::create([
                    'order_id' => $order->id,
                    'code' => OrderStatus::MERCHANT_WASH_IN_PROGRESS,
                    'image_path' => $request->hasFile('image') ? $request->file('image')->store('order_steps', 's3') : null,
                    'remark' => $request->remark,
                    'created_by' => $user->id,
                ]);
            }

            // insert order status
            // 03 - wash in progress
            // 23 - wash in progress
            // 17 - awaiting wash to complete
            $customer_job = null;
            $codes = [OrderStatus::CUSTOMER_WASH_IN_PROGRESS, OrderStatus::MERCHANT_WASH_IN_PROGRESS, OrderStatus::RIDER_AWAITING_WASH_TO_COMPLETE];
            foreach ($codes as $key => $code) {

                // insert order status
                $new_status = OrderStatus::firstOrCreate(
                    ['order_id' => $order->id, 'code' => $code, 'is_done' => true, 'done_at' => now()]
                );

                // get assign user id
                if ($code == OrderStatus::RIDER_AWAITING_WASH_TO_COMPLETE) {
                    $user_id = $rider_id;
                }
                else if ($code == OrderStatus::CUSTOMER_WASH_IN_PROGRESS) {
                    $user_id = $order->user_id;
                }
                else {
                    $user_id = $user->id;
                }

                // insert assign job
                $job = AssignJob::firstOrCreate([
                    'code' => $code,
                    'user_id' => $user_id, 
                    'order_id' => $assign->order_id,
                    'order_status_id' => $new_status->id,
                                    
                ]);
                if ($code == OrderStatus::CUSTOMER_WASH_IN_PROGRESS) {
                    $customer_job = $job;
                }
            }

            // send pn to customer
            event(new \App\Events\CustomerWashInProgress($order->user, $customer_job));

            // update status booking
            $booking->status = Booking::OUTLET;
            $booking->updated_by = $user->id;
            $booking->save();

            $data['order'] = $order;
            return response()->json([
                'status' => true,
                'message' => 'Successfully received bag.',                
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
