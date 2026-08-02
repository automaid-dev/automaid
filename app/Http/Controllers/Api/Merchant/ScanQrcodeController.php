<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Qrcode;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ScanQrcodeController extends Controller
{
    /**
     * [scanQrcode description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function scanQrcode(Request $request)
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

            // check input qrcode
            $validate = Validator::make($request->all(), [
                'type' => 'required',
                'qrcode' => 'required',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            // check if qrcode is exist or not
            $qrcode = Qrcode::where('series_no', $request->qrcode)->where('status', Qrcode::SCANNED)->first();
            if (!$qrcode) {
                return response()->json([
                    'status' => false,
                    'message' => 'Qrcode not found.',
                ]);
            }

            // get user bookings
            $bookings = $qrcode->user->bookings;
            if (count($bookings) == 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found.',
                ]);
            }

            // check booking today
            $today_bookings = [];
            foreach ($bookings as $booking) {
                if (Carbon::parse($booking->pickup_date)->format('Y-m-d') == now()->format('Y-m-d')) {
                    $today_bookings[] = $booking;
                }
            }

            // no booking today
            if (count($today_bookings) == 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found.',
                ]);
            }

            // return data bookings today
            $data['booking'] = $today_bookings;
            return response()->json([
                'status' => true,
                'message' => 'Booking successfully retrieved.',
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
