<?php

namespace App\Http\Controllers\Api\Customer;

use App\Events\CustomerScanBag;
use App\Http\Controllers\Controller;
use App\Models\Bag;
use App\Models\BagScan;
use App\Models\Qrcode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BagController extends Controller
{
    /**
     * [bagPurchased description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function bagPurchased(Request $request)
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

            // check purchased bags
            if (count($user->bag_purchases) == 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'No purchased bag.',
                ]);
            }

            // return data purchased bags
            $data['bag_purchases'] = $user->bag_purchases;
            return response()->json([
                'status' => true,
                'message' => 'Successfully retrieve purchased bags.',
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
     * [bagAssigned description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function bagAssigned(Request $request) 
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

            // check assigned bags
            if (count($user->qrcodes) == 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'No scanned bag.',
                ]);
            }

            // return data assigned bags
            $data['bag_assigns'] = $user->qrcodes;
            return response()->json([
                'status' => true,
                'message' => 'Successfully retrieve assigned bags.',
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
     * [bagQrcode description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function bagQrcode(Request $request)
    {
        try {
            $user = auth('sanctum')->user();            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }

            // get qrcode
            $qrcode = Qrcode::whereNotNull('series_no')->where('status', Qrcode::PENDING)->inRandomOrder()->first();
            if (!$qrcode) {
                return response()->json([
                    'status' => false,
                    'message' => 'Qrcode not found.',
                ]);
            }

            // return data qrcode
            $data['qrcode'] = $qrcode;
            return response()->json([
                'status' => true,
                'message' => 'Qrcode successfully retrieved.',
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
     * [bagScan description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function bagScan(Request $request)
    {
        try {
            $user = auth('sanctum')->user();            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }

            // check if user have purchase the bag
            if (count($user->bag_purchases) == 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'No bag found.',
                ]);
            }
            $purchased = count($user->bag_purchases);

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

            // check total assigned qrcode
            $assigned = Qrcode::where('user_id', $user->id)->get();
            if ($purchased > 0 && $purchased == count($assigned)) {
                return response()->json([
                    'status' => false,
                    'message' => 'All bag already scan.',
                ]);
            }

            // check if qrcode is exist or not
            $qrcode = Qrcode::where('series_no', $request->qrcode)->first();
            if (!$qrcode) {
                return response()->json([
                    'status' => false,
                    'message' => 'Qrcode not found.',
                ]);
            }

            // check if qrcode already taken
            $taken = Qrcode::where('series_no', $request->qrcode)->where('status', Qrcode::SCANNED)->first();
            if ($taken) {
                return response()->json([
                    'status' => false,
                    'message' => 'Qrcode already taken.',
                ]);
            }

            // update qrcode
            $qrcode->user_id = $user->id;
            $qrcode->status = Qrcode::SCANNED;
            $qrcode->scan_by = $user->id;
            $qrcode->scan_at = now();
            $qrcode->save();

            // insert bag scan
            $scan = new BagScan();
            $scan->scan_by = $user->id;
            $scan->type = ($request->type == 'scan') ? 'scan' : 'manual';
            $scan->status = BagScan::SCANNED;
            $scan->save();

            $data['qrocde'] = $qrcode;
            return response()->json([
                'status' => true,
                'message' => 'Successfully scan bag.',
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
