<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BagReceive;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\AssignJob;
use App\Models\Bag;
use App\Models\WashComplete;
use App\Models\Booking;
use Illuminate\Support\Facades\Validator;

class WashController extends Controller
{
    /**
     * [washComplete description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function washComplete(Request $request)
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

            // get rider info
            $rider = $order->rider;
            if (!$rider) {
                return response()->json([
                    'status' => false,
                    'message' => 'Awaiting rider acceptance.',
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
            $status_prev = AssignJob::whereIn('code', [OrderStatus::CUSTOMER_WASH_IN_PROGRESS, OrderStatus::RIDER_AWAITING_WASH_TO_COMPLETE, OrderStatus::MERCHANT_WASH_IN_PROGRESS])
                ->where(['order_id' => $order->id, 'is_accepted' => false])
                ->get();
            if (count($status_prev) != 3) {            
                return response()->json([
                    'status' => false,
                    'message' => 'Merchant not receive bag yet.',
                ]);  
            }

            // initial rider id
            $rider_id = null;

            // update previous status
            foreach ($status_prev as $prev) {

                // get rider id
                if ($prev->code == OrderStatus::RIDER_AWAITING_WASH_TO_COMPLETE) {
                    $rider_id = $prev->user_id;
                }

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

            // insert wash complete
            $complete = WashComplete::firstOrCreate([
                'order_id' => $order->id, 
                'status' => WashComplete::WASH_COMPLETED,
                'created_by' => $user->id,
            ]);

            // optional photo + remark — "Wash completed"
            if ($request->hasFile('image') || $request->filled('remark')) {
                \App\Models\OrderStepPhoto::create([
                    'order_id' => $order->id,
                    'code' => OrderStatus::MERCHANT_AWAITING_RIDER_T0_PICKUP,
                    'image_path' => $request->hasFile('image') ? $request->file('image')->store('order_steps', 's3') : null,
                    'remark' => $request->remark,
                    'created_by' => $user->id,
                ]);
            }

            // insert order status
            // 24 - awaiting rider to pickup
            // 14 - pickup from wash outlet
            $rider_job = null;
            $merchant_job = null;
            $codes = [OrderStatus::MERCHANT_AWAITING_RIDER_T0_PICKUP, OrderStatus::RIDER_PICKUP_FROM_WASH_OUTLET];
            foreach ($codes as $key => $code) {

                // insert order status
                $new_status = OrderStatus::firstOrCreate(
                    ['order_id' => $order->id, 'code' => $code]
                );

                // insert assign job
                $job = AssignJob::firstOrCreate([
                    'code' => $code,
                    'user_id' => ($code == OrderStatus::RIDER_PICKUP_FROM_WASH_OUTLET) ? $rider_id : $user->id, 
                    'order_id' => $assign->order_id,
                    'order_status_id' => $new_status->id,             
                ]);
                if ($code == OrderStatus::MERCHANT_AWAITING_RIDER_T0_PICKUP) {
                    $merchant_job = $job;    
                }
                else {
                    $rider_job = $job;                    
                }
            }

            // send pn to rider
            if ($order->rider) {
                event(new \App\Events\RiderPickupWashOutlet($order->rider->accepted_user, $rider_job));
            }

            // update status booking
            $booking->status = Booking::WASH;
            $booking->updated_by = $user->id;
            $booking->save();

            $data['order'] = $order;
            return response()->json([
                'status' => true,
                'message' => 'wash is completed.',
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



