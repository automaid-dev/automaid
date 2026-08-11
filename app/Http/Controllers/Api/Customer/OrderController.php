<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\AssignJob;
use App\Models\OrderStatus;
use App\Models\Activity;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
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
                'order_id' => 'required',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }
            
            // check order data
            $order = Order::where(['id' => $request->order_id, 'user_id' => $user->id])->first();
            if (!$order) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order not found.',
                ]); 
            }

            // return data order details
            $order->load([
                'booking.order.order_addons.addon', 
                'delivered', 
                'customer_order_statuses',
                'qrcode_users.qrcode',
                'step_photos',
            ]);
            $data['order'] = $order;
            return response()->json([
                'data' => $data,
                'status' => true,
                'message' => 'Order details successfully retrieved.',
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [orderRating description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function orderRating(Request $request)
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
                'order_id' => 'required',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }
            
            // check order data
            $order = Order::where(['id' => $request->order_id, 'user_id' => $user->id])->first();
            if (!$order) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order not found.',
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

            // check order complete
            $complete = $order->delivered;
            if (!$complete) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order is not delivered yet.',
                ]); 
            }

            // update order rating
            $complete->rate_rider_star = $request->rate_rider_star ?? null;
            $complete->rate_rider_comment = $request->rate_rider_comment ?? null;
            $complete->rate_merchant_star = $request->rate_merchant_star ?? null;
            $complete->rate_merchant_comment = $request->rate_merchant_comment ?? null;
            $complete->updated_by = $user->id;
            $complete->is_rated = 1;
            $complete->save();

            $rider_id = null;
            $merchant_id = null;

            // get rider & merchant id            
            $orders = AssignJob::where(['order_id' => $order->id])->whereIn('code', ['16', '26'])->get();
            if (count($orders) == 2) {
                foreach ($orders as $status) {
                    if ($status->code == '16') {
                        $rider_id = $status->user_id;
                    }
                    if ($status->code == '26') {
                        $merchant_id = $status->user_id;
                    }
                }

                // insert activity
                $user_types = [
                    ['customer', $order->user_id], 
                    ['rider', $rider_id], 
                    ['merchant', $merchant_id],
                ];
                foreach ($user_types as $type) {
                    Activity::firstOrCreate(
                        [
                            'order_id' => $order->id, 
                            'user_id' => $type[1], 
                            'user_type' => $type[0],
                            'title' => 'Order Delivered', 
                            'status' => Activity::ACTIVE
                        ],
                    );
                }
            }

            // get booking info
            $data['booking'] = $booking->load([
                'order', 
                'pickup_location', 
                'customer_and_rider_status.status',
                'customer_and_rider_status.rider.user.rider',
                'delivered',
            ]);

            // return data booking
            return response()->json([
                'data' => $data,
                'status' => true,
                'message' => 'Rating successfully added.',
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }


    /**
     * [orderActive description]
     * @return [type] [description]
     */
    public function orderActive()
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }

            // check assign jobs
            $jobs = AssignJob::get();
            

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [orderUpcoming description]
     * @return [type] [description]
     */
    public function orderUpcoming()
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
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
