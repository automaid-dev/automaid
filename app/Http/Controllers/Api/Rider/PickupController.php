<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Models\AssignJob;
use App\Models\Bag;
use App\Models\BagScan;
use App\Models\Booking;
use App\Models\Order;
use App\Models\OrderPickup;
use App\Models\OrderStatus;
use App\Models\ScanHistory;
use App\Models\WashComplete;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PickupController extends Controller
{
    /**
     * [pickupOrder description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function pickupOrder(Request $request)
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

            // get merchant
            $merchant = $order->merchant;
            if (!$merchant) {
                return response()->json([
                    'status' => false,
                    'message' => 'Awaiting merchant acceptance.',
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

            // check status accept order by rider & merchant
            $status_prev = AssignJob::whereIn('code', [OrderStatus::RIDER_READY_FOR_PICKUP, OrderStatus::MERCHANT_AWAITING_BAG_DELIVERY])
                ->where(['order_id' => $assign->order_id, 'is_accepted' => false])
                ->get();
            if (count($status_prev) != 2) {
                return response()->json([
                    'status' => false,
                    'message' => 'Rider/Merchant not accept the order.',
                ]);  
            }

            // update previous status
            foreach ($status_prev as $prev) {

                // update status rider & customer except merchant
                if ($prev->code != OrderStatus::MERCHANT_AWAITING_BAG_DELIVERY) {

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
            }

            // set customer 02 done
            $customer = OrderStatus::where(['order_id' => $assign->order_id, 'code' => '02'])->first();
            $customer->is_done = true;
            $customer->done_at = now();
            $customer->save();

            // insert order pickup
            $pickup = OrderPickup::firstOrCreate([
                'order_id' => $order->id, 
                'status' => OrderPickup::DELIVERY_TO_WASH_OUTLET,
                'created_by' => $user->id,
            ]);

            // optional photo + remark for this handoff step — the flow
            // spec asks for a photo at pickup ("item pickuped") and
            // delivery to outlet; this action covers both in one tap
            // (matches how the rest of this flow is already built —
            // there's no separate "arrived at outlet" confirmation).
            if ($request->hasFile('image') || $request->filled('remark')) {
                \App\Models\OrderStepPhoto::create([
                    'order_id' => $order->id,
                    'code' => OrderStatus::RIDER_DELIVERY_TO_WASH_OUTLET,
                    'image_path' => $request->hasFile('image') ? $request->file('image')->store('order_steps', 's3') : null,
                    'remark' => $request->remark,
                    'created_by' => $user->id,
                ]);
            }

            // insert order status
            // 13 - delivery to wash outlet
            //
            // Deliberately left NOT done here — this action only means
            // the rider has picked up and is now traveling to the
            // outlet, not that they've actually arrived and handed the
            // bag over. Marking it done immediately made the rider's
            // own tracking timeline show this step as complete (blue
            // tick) the moment they left the customer's address, well
            // before the bag was ever actually delivered — confusing
            // since there was no visual difference between "en route"
            // and "delivered." BagController::bagReceive already marks
            // this exact status done at the real moment of completion
            // (when the merchant confirms receiving the bag), so this
            // just creates the row in its correct pending state and
            // lets that later action be the one that flips it.
            $code = OrderStatus::RIDER_DELIVERY_TO_WASH_OUTLET;

            // insert order status (create if missing, without touching
            // is_done — updateOrCreate's empty second argument means an
            // already-existing row's done state is left untouched too,
            // matching the "don't mark this done here" intent above).
            $new_status = OrderStatus::updateOrCreate(
                ['order_id' => $order->id, 'code' => $code],
                []
            );

            // insert assign job
            $job = AssignJob::firstOrCreate([
                'code' => $code,
                'user_id' => $user->id, 
                'order_id' => $assign->order_id,
                'order_status_id' => $new_status->id,              
            ]);

            // update status booking
            $booking->status = Booking::OUTLET;
            $booking->updated_by = $user->id;
            $booking->save();

            // send pn to merchant
            event(new \App\Events\MerchantDeliveryWashOutlet($merchant->accepted_user, $job));

            // send pn to customer
            event(new \App\Events\CustomerDeliveryWashOutlet($order->user, $job));

            $data['order'] = $order;
            return response()->json([
                'status' => true,
                'message' => 'Successfully pickup order.',
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
