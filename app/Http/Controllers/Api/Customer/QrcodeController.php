<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bag;
use Illuminate\Support\Facades\Validator;
use App\Models\Qrcode;

class QrcodeController extends Controller
{
    /**
     * [assignQrcode description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function assignQrcode(Request $request)
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
                    'message' => 'No purchase bag found.',
                ]);
            }

            // get total bag purchases
            $purchase = count($user->bag_purchases);

            // check if user already have qrcodes
            $taken = Qrcode::where(['user_id' => $user->id])->get();
            if (count($taken) > 0) {
                if (count($taken) == $purchase) {

                    // return qrcodes
                    $data['qrcodes'] = $taken;
                    return response()->json([
                        'status' => true,
                        'message' => 'Qrcode successfully retrieved.',
                        'data' => $data,
                    ]);
                }
            }

            // check if there is new purchase bag
            $new = 0;
            if ($purchase > count($taken)) {
                $new = $purchase - count($taken);                
            }

            // get total new bag
            $total = ($new == 0) ? $purchase : $new;

            // get available qrcodes
            $qrcodes = Qrcode::whereNotNull('series_no')->where('status', Qrcode::PENDING)->whereNotIn('id', [$user->id])->inRandomOrder()->limit($total)->get();
            if (count($qrcodes) == 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'No available qrcode.',
                ]);
            }

            // tagging user each qrcode
            foreach ($qrcodes as $qrcode) {
                $qrcode->user_id = $user->id;
                $qrcode->status = Qrcode::SCANNED;
                $qrcode->updated_by = $user->id;
                $qrcode->save();
            }

            $data['qrcodes'] = $qrcodes;
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

}
