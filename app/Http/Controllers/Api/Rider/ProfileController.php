<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ProfileController extends Controller
{
    /**
     * [profile description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function profile(Request $request)
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
            $data['user'] = $user->load([
                'activities.order_complete.order', 
                'activities.order.booking', 
                'activities.order.qrcode_users.qrcode', 
                // Scoped to this user's own Commission record so a
                // merchant/rider only ever sees their own earnings per
                // order, never another party's cut of the same order.
                'activities.order.commission_transactions' => function ($q) use ($user) {
                    $q->whereHas('commission', function ($cq) use ($user) {
                        $cq->where('user_id', $user->id);
                    });
                },
                'wallet.transactions', 
                'rider',
                'tickets',
                'activity_cancels.order.booking',
            ]);
            return response()->json([
                'data' => $data,
                'status' => true,
                'message' => 'Profile successfully retrieved.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [profileUpdate description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function profileUpdate(Request $request)
    {
        try {
            $user = auth('sanctum')->user();            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }
            if (!$user->rider) {
                return response()->json([
                    'status' => false,
                    'message' => 'Merchant info not found.',
                ]);  
            }

            $validateUser = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'mobile_no' => 'required|numeric|min:1',
                'avatar' => 'image|mimes:jpg,png|max:5120',
                'icno' => 'required',

                // 'unit_no' => 'required',
                // 'block' => 'required',
                'address_line_1' => 'required',
                'country_name' => 'required',
                'state_name' => 'required',
                'postcode' => 'required',
                'city' => 'required',

                'emergency_name' => 'required',  
                'emergency_phone' => 'required',
                'emergency_relation' => 'required',
                'bank_name' => 'required',
                'bank_no' => 'required',
            ]);

            if ($validateUser->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ]);
            }

            $avatar = null;
            if ($request->avatar) {
                $avatar = $this->uploadFile($request->avatar);
            }

            // update user
            $user->name = $request->name ?? null;
            $user->mobile_no = $request->mobile_no ?? null;
            $user->icno = $request->icno ?? null;
            $user->avatar = $avatar;

            $user->unit_no = $request->unit_no ?? null;
            $user->block = $request->block ?? null;
            $user->address_line_1 = $request->address_line_1 ?? null;
            $user->address_line_2 = $request->address_line_2 ?? null;
            $user->country_id = $request->country_name ? get_country_id($request->country_name)['id'] : null;
            $user->state_id = $request->state_name ? get_state_id($request->state_name)['id'] : null;
            $user->postcode = $request->postcode ?? null;
            $user->city = $request->city ?? null;
            $user->updated_by = $user->id;
            $user->latitude = $request->latitude ?? null;
            $user->longitude = $request->longitude ?? null;
            $user->save();

            // update rider
            $rider = $user->rider;          
            $rider->emergency_name = $request->emergency_name ?? null;            
            $rider->emergency_phone = $request->emergency_phone ?? null;            
            $rider->country_code_emergency = $request->country_code_emergency ?? null;            
            $rider->emergency_relation = $request->emergency_relation ?? null;            
            $rider->bank_name = $request->bank_name ?? null;            
            $rider->bank_no = $request->bank_no ?? null;      
            $rider->updated_by = $user->id;     
            $rider->save();

            $data['user'] = $user;
            return response()->json([
                'data' => $data,
                'status' => true,
                'message' => 'Profile rider successfully updated.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [uploadFile description]
     * @param  [type] $file [description]
     * @return [type]       [description]
     */
    public function uploadFile($file)
    {
        $ext = $file->extension();
        $path = '/automaid/images/riders/' . uniqid().date('Ymdhis') . '.' . $ext;
        $manager = new ImageManager(new Driver());
        $img = $manager->read($file);
        // $img->resize(width: 200);
        $pointer = $img->encode()->toFilePointer();
        Storage::disk('s3')->put($path, $pointer, 'public');  
        return $path;
    }


}
