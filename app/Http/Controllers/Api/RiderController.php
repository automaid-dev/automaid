<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\CityUser;
use App\Models\Outlet;
use App\Models\Rider;
use App\Models\User;
use App\Notifications\WelcomeMailNotification;
use App\Services\OneWaySmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Seshac\Otp\Otp;

class RiderController extends Controller
{
    /**
     * [register description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function register(Request $request)
    {
        try {
            $validateEmail = Validator::make($request->all(), [
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->whereNull('deleted_at')],             
            ]);
            if ($validateEmail->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateEmail->errors()
                ]);
            }

            // check user — include soft-deleted rows too, since `email`
            // has a hard unique DB constraint: only checking non-deleted
            // rows here would let validation pass for a soft-deleted
            // user's email, then crash on a duplicate-key error at the
            // INSERT step below (same class of bug already fixed on the
            // customer registration flow — this controller just never
            // got the same fix).
            $user = User::withTrashed()->where('email', $request->email)->first();

            // new user, or a previously soft-deleted account re-registering
            if (!$user || $user->trashed()) {            
                $validate = Validator::make($request->all(), [
                    'name' => 'required|string|max:255',
                    'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->whereNull('deleted_at')->ignore($user?->id)],
                    'country_code_mobile' => 'required',
                    'mobile_no' => 'required|numeric|min:1',
                    'password' => 'required|string|min:8|confirmed',
                    'password_confirmation' => 'required|min:8',
                    'icno' => 'required',

                    'address_line_1' => 'required',
                    'address_line_2' => 'required',
                    'country_name' => 'required',
                    'state_name' => 'required',
                    'postcode' => 'required',
                    'city' => 'required',

                    'type_rider' => 'required',    
                    'type_vehicle' => 'required',  
                    'emergency_name' => 'required',
                    'country_code_emergency' => 'required',
                    'emergency_phone' => 'required',
                    'emergency_relation' => 'required',
                    'plate_no' => 'required',
                    'vehicle_make' => 'required',
                    'vehicle_model' => 'required', 
                    // 'bank_name' => 'required',  
                    // 'bank_no' => 'required',  
                    
                    'ic_front' => 'image|mimes:jpg,png|max:10240',
                    'ic_back' => 'image|mimes:jpg,png|max:10240',
                    'license_front' => 'image|mimes:jpg,png|max:10240',
                    'license_back' => 'image|mimes:jpg,png|max:10240',
                    'jpj_grant' => 'image|mimes:jpg,png|max:10240',

                    'latitude' => 'required',
                    'longitude' => 'required',
                ]);

                if ($validate->fails()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'validation error',
                        'errors' => $validate->errors()
                    ]);
                }

                // insert user rider — restore-and-reuse the row if this
                // email belongs to a previously soft-deleted account,
                // rather than always inserting a fresh row (which would
                // crash on the hard unique constraint for `email`).
                if ($user && $user->trashed()) {
                    $user->restore();
                    $user->name = $request->name ?? null;
                    $user->country_code_mobile = $request->country_code_mobile ?? null;
                    $user->mobile_no = $request->mobile_no ?? null;
                    $user->password = Hash::make($request->password);
                    $user->icno = $request->icno ?? null;
                    $user->id_type = match (strtoupper((string) $request->id_type)) {
                        'NRIC' => 1,
                        'PASSPORT' => 2,
                        default => null,
                    };
                    $user->status = User::PENDING;
                    $user->is_active = false;
                    $user->email_verified_at = now();
                    $user->address_line_1 = $request->address_line_1 ?? null;
                    $user->address_line_2 = $request->address_line_2 ?? null;
                    $user->country_id = $request->country_name ? get_country_id($request->country_name)['id'] : null;
                    $user->state_id = $request->state_name ? get_state_id($request->state_name)['id'] : null;
                    $user->postcode = $request->postcode ?? null;
                    $user->city = $request->city ?? null;
                    $user->latitude = $request->latitude ?? null;
                    $user->longitude = $request->longitude ?? null;
                    $user->save();
                } else {
                    $user = User::create([
                        'name' => $request->name ?? null,
                        'email' => $request->email ?? null,
                        'country_code_mobile' => $request->country_code_mobile ?? null,
                        'mobile_no' => $request->mobile_no ?? null,
                        'password' => Hash::make($request->password),
                        'icno' => $request->icno ?? null,
                        'id_type' => match (strtoupper((string) $request->id_type)) {
                            'NRIC' => 1,
                            'PASSPORT' => 2,
                            default => null,
                        },
                        'status' => User::PENDING,
                        'is_active' => false,
                        'email_verified_at' => now(),

                        'address_line_1' => $request->address_line_1 ?? null,
                        'address_line_2' => $request->address_line_2 ?? null,
                        'country_id' => $request->country_name ? get_country_id($request->country_name)['id'] : null,
                        'state_id' => $request->state_name ? get_state_id($request->state_name)['id'] : null,
                        'postcode' => $request->postcode ?? null,
                        'city' => $request->city ?? null,
                        
                        'latitude' => $request->latitude ?? null,
                        'longitude' => $request->longitude ?? null,
                    ]);
                }
                $user->assignRole('rider');

                $ic_front = null;
                if ($request->ic_front) {
                    $ic_front = $this->uploadFile($request->ic_front);
                }

                $ic_back = null;
                if ($request->ic_back) {
                    $ic_back = $this->uploadFile($request->ic_back);
                }

                $license_front = null;
                if ($request->license_front) {
                    $license_front = $this->uploadFile($request->license_front);
                }

                $license_back = null;
                if ($request->license_back) {
                    $license_back = $this->uploadFile($request->license_back);
                }

                $jpj_grant = null;
                if ($request->jpj_grant) {
                    $jpj_grant = $this->uploadFile($request->jpj_grant);
                }

                $rider = Rider::firstOrNew(['user_id' => $user->id]);
                $rider->type_rider = $request->type_rider ?? null;
                $rider->type_vehicle = $request->type_vehicle ?? null;
                $rider->emergency_name = $request->emergency_name ?? null;
                $rider->emergency_phone = $request->emergency_phone ?? null;
                $rider->emergency_relation = $request->emergency_relation ?? null;
                $rider->country_code_emergency = $request->country_code_emergency ?? null;
                $rider->plate_no = $request->plate_no ?? null;
                $rider->vehicle_make = $request->vehicle_make ?? null;
                $rider->vehicle_model = $request->vehicle_model ?? null;
                $rider->vehicle_color = $request->vehicle_color ?? null;
                $rider->status = Rider::PENDING;

                $rider->ic_front = $ic_front;
                $rider->ic_back = $ic_back;
                $rider->license_front = $license_front;
                $rider->license_back = $license_back;
                $rider->jpj_grant = $jpj_grant;

                $rider->bank_name = $request->bank_name ?? null;
                $rider->bank_no = $request->bank_no ?? null;
                $rider->save();

                // get city
                $city = City::where('name', strtolower($request->city))->first();
                if ($city) {

                    // insert city user
                    $city_user = new CityUser();
                    $city_user->user_id = $user->id;
                    $city_user->city_id = $city->id;
                    $city_user->is_active = true;
                    $city_user->created_by = $user->id;
                    $city_user->save();
                }

                $sms = OneWaySmsService::make();
                $send = $sms->processOtp($user->mobile_no);
                if ($send['status'] != 'success') {
                    return response()->json([
                        'status' => $send['status'],
                        'message' => $send['message'],
                    ]);
                }
                return response()->json([
                    'status' => true,
                    'message' => 'OTP has been sent to your mobile number. Please verify the OTP within 10 minutes.',
                    'user_id' => $user->id,
                ]);
            }

            // existing user
            else {

                // status still pending
                if ($user->status == User::PENDING) {

                    // send new otp
                    $sms = OneWaySmsService::make();
                    $send = $sms->processOtp($user->mobile_no);
                    if ($send['status'] != 'success') {
                        return response()->json([
                            'status' => $send['status'],
                            'message' => $send['message'],
                        ]);
                    }
                    return response()->json([
                        'status' => true,
                        'message' => 'OTP has been sent to your mobile number. Please verify the OTP within 10 minutes.',
                        'user_id' => $user->id,
                    ]);
                }

                // status already active
                else {
                    return response()->json([
                        'status' => false,
                        'message' => 'User already active.',
                    ]);
                }
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
        $path = '/automaid/images/riders/' . uniqid().date('Ymdhis') . '.' . $ext;
        $manager = new ImageManager(new Driver());
        $img = $manager->read($file);
        // $img->resize(width: 200);
        $pointer = $img->encode()->toFilePointer();
        Storage::disk('s3')->put($path, $pointer, 'public');  
        return $path;
    }

    /**
     * [changeNumber description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function changeNumber(Request $request)
    {
        try {
            $validateUser = Validator::make($request->all(), [
                'user_id' => 'required',  
                'country_code_mobile' => 'required',
                'mobile_no' => 'required|numeric|min:1',            
            ]);
            if ($validateUser->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ]);
            }

            // check user
            $user = User::find($request->user_id);
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not exist.',
                ]);
            }

            // update user
            $user->country_code_mobile = $request->country_code_mobile;
            $user->mobile_no = $request->mobile_no;
            $user->save();

            // send new otp
            $sms = OneWaySmsService::make();
            $send = $sms->processOtp($user->mobile_no);
            if ($send['status'] != 'success') {
                return response()->json([
                    'status' => $send['status'],
                    'message' => $send['message'],
                ]);
            }
            return response()->json([
                'status' => true,
                'message' => 'OTP has been sent to your mobile number. Please verify the OTP within 10 minutes.',
                'user_id' => $user->id,
            ]);
            
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [verifyRegisterRider description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function verifyRegisterRider(Request $request)
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

            // check user
            $user = User::find($request->user_id);
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not exist.',
                ]);
            }

            // verify otp
            $verify = Otp::validate($user->mobile_no, $request->token);
            if (!$verify->status) {
                return response()->json([
                    'status' => $verify->status,
                    'message' => $verify->message,
                ]);
            }
            else {

                // check if status already update
                if ($user->is_active && $user->status == User::ACTIVE) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Account already active.',
                    ]);
                }

                // update rider status
                if ($user->rider) {
                    $rider = $user->rider;
                    $rider->status = Rider::ACTIVE;
                    $rider->save();
                }

                // generate api token
                $token = $user->createToken('token-name')->plainTextToken;

                // update user status
                $user->api_token = $token;
                $user->is_active = true;
                $user->status = User::ONBOARDING;
                $user->otp_verified = true;
                $user->otp_verified_date = now();
                $user->save();

                // get full path image
                $rider->ic_front_url = ($rider->ic_front) ? Storage::disk('s3')->url($rider->ic_front) : null;
                $rider->ic_back_url = ($rider->ic_back) ? Storage::disk('s3')->url($rider->ic_back) : null;
                $rider->license_front_url = ($rider->license_front) ? Storage::disk('s3')->url($rider->license_front) : null;
                $rider->license_back_url = ($rider->license_back) ? Storage::disk('s3')->url($rider->license_back) : null;
                $rider->jpj_grant_url = ($rider->jpj_grant) ? Storage::disk('s3')->url($rider->jpj_grant) : null;

            }
            
            $user->load('roles');
            return response()->json([
                'user' => $user,
                'status' => true,
                'message' => 'Thank you! Rider registration is completed.',
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

}


