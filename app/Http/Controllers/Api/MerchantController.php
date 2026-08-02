<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\CityUser;
use App\Models\Merchant;
use App\Models\Outlet;
use App\Models\User;
use App\Notifications\WelcomeMailNotification;
use App\Services\OneWaySmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Seshac\Otp\Otp;

class MerchantController extends Controller
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
                'email' => 'required|string|email|max:255|unique:users',             
            ]);            
            if ($validateEmail->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateEmail->errors()
                ]);
            }

            // check user
            $user = User::where('email', $request->email)->first();            

            // new user
            if (!$user) {
                $validate = Validator::make($request->all(), [
                    'name' => 'required|string|max:255',
                    'email' => 'required|string|email|max:255|unique:users',
                    'country_code_mobile' => 'required',
                    'mobile_no' => 'required|numeric|min:1',
                    'password' => 'required|string|min:8|confirmed',
                    'password_confirmation' => 'required|min:8',
                    'icno' => 'required',
                    'id_type' => 'required',

                    'country_name' => 'required',
                    'state_name' => 'required',
                    'postcode' => 'required',
                    'city' => 'required',

                    'type_merchant' => 'required',    
                    'washer_quantity' => 'required|numeric',  
                    'dryer_quantity' => 'required|numeric',
                    'ic_front' => 'image|mimes:jpg,png|max:10240',
                    'ic_back' => 'image|mimes:jpg,png|max:10240',
                    'ssm_cert' => 'image|mimes:jpg,png|max:10240',
                    'ssm_no' => 'required',

                    'company_name' => 'required',
                    // 'bank_name' => 'required',
                    // 'bank_no' => 'required',

                    'latitude' => 'required',
                    'longitude' => 'required',
                    
                    'business_option' => 'required',
                    'service_categories' => 'required',
                ]);

                if ($validate->fails()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'validation error',
                        'errors' => $validate->errors()
                    ]);
                }

                // check outlet
                $chk_outlet = Outlet::where(['name' => $request->company_name, 'city' => $request->city, 'address_line_1' => $request->address_line_1])->first();
                if (!$chk_outlet) {

                    // insert new outlet
                    $outlet = new Outlet();
                    $outlet->name = $request->company_name ?? null;
                    $outlet->slug = Str::slug($outlet->name);
                    $outlet->unit_no = $request->unit_no ?? null;
                    $outlet->floor = $request->floor ?? null;
                    $outlet->block = $request->block ?? null;
                    $outlet->address_line_1 = $request->address_line_1 ?? null;
                    $outlet->address_line_2 = $request->address_line_2 ?? null;
                    $outlet->address_line_3 = $request->address_line_3 ?? null;
                    $outlet->postcode = $request->postcode ?? null;
                    $outlet->city = $request->city ?? null;
                    $outlet->state_id = $request->state_name ? get_state_id($request->state_name)['id'] : null;
                    $outlet->country_id = $request->country_name ? get_country_id($request->country_name)['id'] : null;
                    $outlet->latitude = $request->latitude ?? null;
                    $outlet->longitude = $request->longitude ?? null;
                    $outlet->status = Outlet::PENDING;
                    $outlet->save();
                }

                // insert new merchant
                $user = User::create([
                    'name' => $request->name ?? null,
                    'email' => $request->email ?? null,
                    'country_code_mobile' => $request->country_code_mobile ?? null,                    
                    'mobile_no' => $request->mobile_no ?? null,
                    'password' => Hash::make($request->password),
                    'icno' => $request->icno ?? null,
                    'id_type' => $request->id_type ?? null,
                    'status' => User::PENDING,
                    'is_active' => false,
                    'email_verified_at' => now(),
                    'icno' => $request->icno ?? null,

                    'address_line_1' => $request->address_line_1 ?? null,
                    'address_line_2' => $request->address_line_2 ?? null,
                    'country_id' => $request->country_name ? get_country_id($request->country_name)['id'] : null,
                    'state_id' => $request->state_name ? get_state_id($request->state_name)['id'] : null,
                    'postcode' => $request->postcode ?? null,
                    'city' => $request->city ?? null,

                    'latitude' => $request->latitude ?? null,
                    'longitude' => $request->longitude ?? null,
                ]);
                $user->assignRole('merchant');

                $ic_front = null;
                if ($request->ic_front) {
                    $ic_front = $this->uploadFile($request->ic_front);
                }

                $ic_back = null;
                if ($request->ic_back) {
                    $ic_back = $this->uploadFile($request->ic_back);
                }

                $ssm_cert = null;
                if ($request->ssm_cert) {
                    $ssm_cert = $this->uploadFile($request->ssm_cert);
                }

                // insert merchant
                $merchant = new Merchant();
                $merchant->user_id = $user->id;
                $merchant->outlet_id = $request->outlet_id;
                $merchant->type_merchant = $request->type_merchant ?? null;
                $merchant->washer_quantity = $request->washer_quantity ?? null;
                $merchant->dryer_quantity = $request->dryer_quantity ?? null;

                $merchant->status = Merchant::PENDING;
                $merchant->ic_front = $ic_front ?? null;
                $merchant->ic_back = $ic_back ?? null;
                $merchant->ssm_cert = $ssm_cert ?? null;
                $merchant->ssm_no = $request->ssm_no ?? null;

                $merchant->unit_no = $request->unit_no ?? null;
                $merchant->block = $request->block ?? null;
                $merchant->address_line_1 = $request->address_line_1 ?? null;
                $merchant->address_line_2 = $request->address_line_2 ?? null;
                $merchant->country_id = $request->country_name ? get_country_id($request->country_name)['id'] : null;
                $merchant->state_id = $request->state_name ? get_state_id($request->state_name)['id'] : null;
                $merchant->postcode = $request->postcode ?? null;
                $merchant->city = $request->city ?? null;

                $merchant->company_name = $request->company_name ?? null;
                $merchant->bank_name = $request->bank_name ?? null;
                $merchant->bank_no = $request->bank_no ?? null;

                $merchant->business_option = $request->business_option ?? null;
                $merchant->service_categories = $request->service_categories ?? null;
                $merchant->save();

                // update user
                $user->unit_no = $request->unit_no ?? null;
                $user->block = $request->block ?? null;
                $user->address_line_1 = $request->address_line_1 ?? null;
                $user->address_line_2 = $request->address_line_2 ?? null;
                $user->country_id = $request->country_name ? get_country_id($request->country_name)['id'] : null;
                $user->state_id = $request->state_name ? get_state_id($request->state_name)['id'] : null;
                $user->postcode = $request->postcode ?? null;
                $user->city = $request->city ?? null;
                $user->save();

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
     * [verifyRegisterMerchant description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function verifyRegisterMerchant(Request $request)
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

            $verify = Otp::validate($user->mobile_no, $request->token);
            if (!$verify->status) {
                return response()->json([
                    'status' => $verify->status,
                    'message' => $verify->message,
                ]);
            }
            else {
                if ($user->is_active && $user->status == User::ACTIVE) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Account already active.',
                    ]);
                }

                // update merchant status
                if ($user->merchant) {
                    $merchant = $user->merchant;
                    $merchant->status = Merchant::ACTIVE;
                    $merchant->save();
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
                $merchant->ic_front_url = ($merchant->ic_front) ? Storage::disk('s3')->url($merchant->ic_front) : null;
                $merchant->ic_back_url = ($merchant->ic_back) ? Storage::disk('s3')->url($merchant->ic_back) : null;
                $merchant->ssm_cert_url = ($merchant->ssm_cert) ? Storage::disk('s3')->url($merchant->ssm_cert) : null;
            }

            $user->load('roles');            
            return response()->json([
                'user' => $user,
                'status' => true,
                'message' => 'Thank you! Merchant registration is completed.',
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
        $path = '/automaid/images/merchants/' . uniqid().date('Ymdhis') . '.' . $ext;
        $manager = new ImageManager(new Driver());
        $img = $manager->read($file);
        // $img->resize(width: 200);
        $pointer = $img->encode()->toFilePointer();
        Storage::disk('s3')->put($path, $pointer, 'public');  
        return $path;
    }

    /**
     * [searchOutlet description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function searchOutlet(Request $request)
    {
        try {
            $name = $request->name ?? null;
            $outlets = Outlet::orderBy('name', 'asc')
            ->when($name, function($query) use ($name) {
                $query->where('name', 'like', '%' . $name . '%');
            })
            ->get();

            return response()->json([
                'outlets' => $outlets,
                'status' => true,
                'message' => 'Outlets retrieved successfully.',
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

}


