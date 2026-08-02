<?php

namespace App\Http\Controllers\Api\Customer;

use App\Models\Booking;
use App\Models\AssignJob;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class HomeController extends Controller
{
    /**
     * [home description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function home(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }

            // get booking info (exclude delivered)
            $bookings = Booking::where('user_id', $user->id)
                ->whereDoesntHave('delivered') 
                ->where('status', '!=', Booking::CANCEL)
                ->with([
                    'order.order_addons.addon', 
                    'pickup_location',
                    'order.qrcode_users.qrcode',
                ])
                ->latest()
                ->get();

            // if not delivered, load additional relations
            $bookings->map(function ($booking) {
                $booking->load([
                    'customer_and_rider_status.status',
                    'customer_and_rider_status.rider.user.rider',
                ]);
            });

            $data['bookings'] = $bookings;
            return response()->json([
                'data' => $data,
                'status' => true,
                'message' => 'Dashboard customer successfully retrieved.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }


}
