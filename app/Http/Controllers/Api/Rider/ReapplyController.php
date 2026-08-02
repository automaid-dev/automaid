<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ReapplyController extends Controller
{
    /**
     * [reApplyUpdate description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function reApplyUpdate(Request $request)
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

            // validate input
            $validate = Validator::make($request->all(), [
                'name' => 'required',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            // get rider info
            if (!$user->rider) {
                return response()->json([
                    'status' => false,
                    'message' => 'Rider is not exist.',
                ]); 
            }
            $rider = $user->rider;

            $ic_front = $rider->ic_front;
            if (isset($request->ic_front) && $request->ic_front !== null) {
                $ic_front = $this->uploadFile($request->ic_front);
            }

            $ic_back = $rider->ic_back;
            if (isset($request->ic_back) && $request->ic_back !== null) {
                $ic_back = $this->uploadFile($request->ic_back);
            }

            $license_front = $rider->license_front;
            if (isset($request->license_front) && $request->license_front !== null) {
                $license_front = $this->uploadFile($request->license_front);
            }

            $license_back = $rider->license_back;
            if (isset($request->license_back) && $request->license_back !== null) {
                $license_back = $this->uploadFile($request->license_back);
            }

            $jpj_grant = $rider->jpj_grant;
            if (isset($request->jpj_grant) && $request->jpj_grant !== null) {
                $jpj_grant = $this->uploadFile($request->jpj_grant);
            }

            // update user info
            $user->name = $request->name ?? null;
            $user->id_type = $request->id_type ?? null;
            $user->icno = $request->icno ?? null;

            // update address
            $user->address_line_1 = $request->address_line_1 ?? null;
            $user->address_line_2 = $request->address_line_2 ?? null;
            $user->country_id = $request->country_name ? get_country_id($request->country_name)['id'] : null;
            $user->state_id = $request->state_name ? get_state_id($request->state_name)['id'] : null;
            $user->postcode = $request->postcode ?? null;
            $user->city = $request->city ?? null;
            $user->status = User::ONBOARDING;
            $user->save();

            // update emergency contact
            $rider->emergency_name = $request->emergency_name ?? null;
            $rider->country_code_emergency = $request->country_code_emergency ?? null;
            $rider->emergency_phone = $request->emergency_phone ?? null;
            $rider->emergency_relation = $request->emergency_relation ?? null;

            // update rider info
            $rider->type_vehicle = $request->type_vehicle ?? null;
            $rider->plate_no = $request->plate_no ?? null;
            $rider->vehicle_make = $request->vehicle_make ?? null;
            $rider->vehicle_model = $request->vehicle_model ?? null;
            $rider->vehicle_color = $request->vehicle_color ?? null;

            // update verification
            $rider->ic_front = $ic_front;
            $rider->ic_back = $ic_back;
            $rider->license_front = $license_front;
            $rider->license_back = $license_back;
            $rider->jpj_grant = $jpj_grant;

            // update bank info
            $rider->bank_name = $request->bank_name ?? null;
            $rider->bank_no = $request->bank_no ?? null;
            $rider->save();

            // return success
            return response()->json([
                'status' => true,
                'message' => 'Rider successfully re-apply.',
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


