<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use App\Services\OneWaySmsService;
use App\Models\MobileChange;
use App\Models\User;
use Seshac\Otp\Otp;

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
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }

            // load lists of user profile
            $data['user'] = $user->load([
                'bags',
                'bag_scans',
                'addresses.state',
                'addresses.country',
                'activities.order.booking.pickup_location', 
                'activities.order.booking.customer_and_rider_status.status', 
                'activities.order.booking.customer_and_rider_status.rider.user.rider', 
                'activities.order.booking.delivered', 
                'activities.order.payment.recurrings', 
                'activities.order.qrcode_users.qrcode', 
                'subscribe.order', 
                'unsubscribes.subscription',
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
            $validateUser = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                // 'mobile_no' => 'required|numeric|min:1',
                // 'dob' => 'required',
                'avatar' => 'image|mimes:jpg,png|max:5120',
            ]);

            if ($validateUser->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ]);
            }

            // set avatar
            $avatar = $user->avatar;
            if ($request->avatar) {
                $avatar = $this->uploadFile($request->avatar);
            }

            // update user
            $user->name = $request->name ?? null;
            $user->dob = $request->dob ?? null;
            $user->avatar = $avatar;
            $user->save();

            // check if request change mobile no
            if ($request->mobile_no) {

                // send otp to new number
                $sms = OneWaySmsService::make();
                $send = $sms->processOtp($request->mobile_no);
                if ($send['status'] != 'success') {
                    return response()->json([
                        'status' => $send['status'],
                        'message' => $send['message'],
                    ]);
                }

                // insert mobile changes
                $change = new MobileChange();
                $change->user_id = $user->id;
                $change->new_mobile = $request->mobile_no;
                $change->save();

                return response()->json([
                    'status' => true,
                    'message' => 'OTP has been sent to your mobile number. Please verify the OTP within 10 minutes.',
                    'user_id' => $user->id,
                ]);
            }

            // update profile
            else {
                return response()->json([
                    'status' => true,
                    'message' => 'Profile successfully updated.',
                    'user_id' => $user->id,
                ]);
            }

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
        $path = '/automaid/images/customers/' . uniqid().date('Ymdhis') . '.' . $ext;
        $manager = new ImageManager(new Driver());
        $img = $manager->read($file);
        // $img->resize(width: 200);
        $pointer = $img->encode()->toFilePointer();
        Storage::disk('s3')->put($path, $pointer, 'public');  
        return $path;
    }

    /**
     * [verifyUpdate description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function verifyUpdate(Request $request)
    {
        try {
            $validateUser = Validator::make($request->all(), [
                'user_id' => 'required',              
                'token' => 'required',              
            ], [
                'token.required' => 'OTP is required.'
            ]);
            if ($validateUser->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ]);
            }

            $user = User::find($request->user_id);
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not exist.',
                ]);
            }

            // check request change
            if (!$user->mobile_change) {
                return response()->json([
                    'status' => false,
                    'message' => 'No record request.',
                ]);
            }

            // new mobile no
            $new_mobile_no = $user->mobile_change->new_mobile;

            // validate new mobile no & token
            $verify = Otp::validate($new_mobile_no, $request->token);
            if (!$verify->status) {
                return response()->json([
                    'status' => $verify->status,
                    'message' => $verify->message,
                ]);
            }
            else {

                // update user mobile
                $user->mobile_no = $new_mobile_no;      
                $user->save();

                // remove mobile change
                $user->mobile_change->delete();
            }

            $user->load('roles');
            return response()->json([
                'status' => true,
                'message' => 'Your mobile number has been updated successfully.',
                'user' => $user,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }




}
