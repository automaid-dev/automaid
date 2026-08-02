<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Models\AssignJob;
use App\Models\Bag;
use App\Models\Qrcode;
use App\Models\OrderStatus;
use App\Models\Order;
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

            // check if other user have accepted the job
            $taken = AssignJob::where(['code' => OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE, 'order_id' => $assign->order_id, 'is_accepted' => true])->first();
            if ($taken) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job already taken.',
                ]);  
            }

            // update status assign if not accept yet
            $assign->is_accepted = true;
            $assign->accepted_at = now();
            $assign->accepted_by = $user->id;            
            $assign->save();

            // update order status
            $status->is_done = true;
            $status->done_at = now();
            $status->updated_by = auth()->user()->id;
            $status->save();

            // add new order status
            $new_status = OrderStatus::firstOrCreate(
                [
                    'order_id' => $assign->order_id, 
                    'code' => OrderStatus::MERCHANT_AWAITING_BAG_DELIVERY,
                ]
            );

            // insert assign job
            $pickup = AssignJob::firstOrCreate([
                'code' => OrderStatus::MERCHANT_AWAITING_BAG_DELIVERY,
                'user_id' => $user->id, 
                'order_id' => $assign->order_id,
                'order_status_id' => $new_status->id,                
            ]);
            
            // set previous assignee queue false
            AssignJob::where([
                'code' => OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE,
                'order_id' => $assign->order_id,
            ])->whereNotIn('id', [$assign->id])
            ->update(['is_queue' => false]);
            
            $data['assign'] = $assign->load([
                'user.merchant',
                'booking.merchant_order_status.status', 
            ]);
            return response()->json([
                'status' => true,
                'message' => 'Merchant successfully accept order.',
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
                    'merchant_order_statuses.status',
                    'order_addons.addon',
                    'delivered',
                    'qrcode_users.qrcode',
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
                    'user.merchant', 
                    'order.booking.pickup_location', 
                    'order.merchant_order_statuses.status',
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
