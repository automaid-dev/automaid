<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ForgotPasswordEmail;
use App\Mail\RegisterEmail;
use App\Mail\SendLinkResetPassword;
use App\Models\Outlet;
use App\Models\Postcode;
use App\Models\ResetCodePassword;
use App\Models\User;
use App\Models\WaitingList;
use App\Notifications\WelcomeMailNotification;
use App\Services\OneSignalService;
use App\Services\OneWaySmsService;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Seshac\Otp\Otp;

class AuthController extends Controller
{
    /**
     * [login description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
                // 'device_id' => 'required',
            ]);

            $user = User::where('email', $request->email)->first();
            if (! $user || ! Hash::check($request->password, $user->password)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }            
            $data['user'] = $user;

            // registration not complete 
            if (!$user->is_active || $user->status == User::PENDING) {
                $user->load('roles');
                return response()->json([
                    'status' => false,
                    'message' => 'Registration is not complete.',
                    'data' => $data,
                ],200);
            }

            // status is inactive
            if ($user->status == User::INACTIVE) {
                return response()->json([
                    'status' => true,
                    'message' => 'Status is inactive.',
                    'data' => $data,
                ],200);
            }

            // update device_id
            $user->device_id = $request->device_id ?? null;
            $user->save();

            // check if rider/merchant is rejected
            if ($user->hasRole('rider') || $user->hasRole('merchant')) {
                if ($user->status == User::REJECTED) {
                    $user->load('roles');
                    return response()->json([
                        'message' => 'Application has been rejected and please re-apply again.',
                        'status' => true,
                        'user' => $user,
                        'token' => $user->createToken('token-name')->plainTextToken,
                    ]);
                }
            }
            
            $user->load('roles');
            return response()->json([
                'status' => true,
                'user' => $user,
                'token' => $user->createToken('token-name')->plainTextToken,
            ], 200);
            
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [resendOtp description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function resendOtp(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'email' => 'required',              
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not exist.',
                ]);
            }

            if ($user->is_active && $user->status == User::ACTIVE) {
                return response()->json([
                    'status' => true,
                    'message' => 'Account already active.',
                ]);
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

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

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
            $email = $request->email;
            $user = User::where('email', $email)->first();

            // new user
            if (!$user) {
                $validateUser = Validator::make($request->all(), [
                    'name' => 'required|string|max:255',
                    'email' => 'required|string|email|max:255|unique:users',
                    'mobile_no' => [
                        'required',
                        'numeric',
                        'regex:/^60\d{9,10}$/',
                    ],
                    'password' => 'required|string|min:8|confirmed',
                    'password_confirmation' => 'required|min:8'                
                ]);

                if ($validateUser->fails()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'validation error',
                        'errors' => $validateUser->errors()
                    ]);
                }

                // insert user
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'mobile_no' => $request->mobile_no,
                    'password' => Hash::make($request->password),
                    'status' => User::PENDING,
                    'is_active' => false,
                    'email_verified_at' => now(),
                    'dob' => $request->dob ?? null,
                ]);
                $user->assignRole('customer');

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
     * [verifyRegister description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function verifyRegister(Request $request)
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

                // generate api token
                $token = $user->createToken('token-name')->plainTextToken;

                // update user status
                $user->api_token = $token;                
                $user->is_active = true;
                $user->status = User::ACTIVE;
                $user->otp_verified = true;
                $user->otp_verified_date = now();
                $user->save();

                // save notification
                // $user->notify(new WelcomeMailNotification($user));

                // send email
                $subject = 'Welcome to Auto Maid,' . ' "' . $user->name . '" ';
                $emailContent = (new RegisterEmail($user->name, $user->email, $subject))->render();
                $onesignal = new OneSignalService();
                $onesignal->sendEmail(
                    $user->email,
                    $subject,
                    $emailContent,
                );
            }

            $user->load('roles');
            return response()->json([
                'status' => true,
                'message' => 'Thank you! The registration is completed.',
                'user' => $user,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    } 

    /**
     * [passwordEmail description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function passwordEmail(Request $request)
    {
        try {
            $validateUser = Validator::make($request->all(),
            [
                'email' => 'required|string|email|exists:users',
            ]);

            if ($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ], 401);
            }

            // check user status
            $user = User::where('email', $request->email)->first();
            if ($user) {

                // user is active
                if ($user->status == User::ACTIVE) {

                    // remove existing reset code
                    ResetCodePassword::where('email', $request->email)->delete();

                    // create new token
                    $token = Str::random(64);            
                    DB::table('password_reset_tokens')->insert([
                        'email' => $request->email, 
                        'token' => $token, 
                        'created_at' => Carbon::now()
                    ]);

                    $url = route('api.auth.password.verify.token', [$token]);
                    // Mail::to($request->email)->send(new SendLinkResetPassword($url));

                    // send email reset password
                    $subject = 'Auto Maid: RESET PASSWORD';
                    $emailContent = (new ForgotPasswordEmail($user->email, $subject, $url))->render();
                    $onesignal = new OneSignalService();
                    $onesignal->sendEmail(
                        $user->email,
                        $subject,
                        $emailContent,
                    );

                    // return message
                    return response()->json([
                        'status' => true,
                        'message' => 'Successfully send email to reset the password.',
                    ], 200);
                }

                // user not active 
                else {
                    return response()->json([
                        'status' => false,
                        'message' => 'User is not active.',
                    ]);
                }
            }

            else {
                return response()->json([
                    'status' => false,
                    'message' => 'User not exist.',
                ]);
            }
        }

        catch (\Throwable $th) {
            return response()->json([
                'status'=>false,
                'message'=>$th->getMessage(),
            ], 500);
        }
    }

    /**
     * [verifyToken description]
     * @param  [type] $token [description]
     * @return [type]        [description]
     */
    public function verifyToken($token)
    {
        try {
            $passwordReset = ResetCodePassword::firstWhere('token', $token);
            if (!$passwordReset) {
                return response()->json([
                    'status' => false,
                    'message' => 'Token is invalid!',
                ], 401);
            }
            if ($passwordReset->created_at > now()->addHour()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Password token is expired.',
                ], 401);
            }
            return redirect()->route('auth.password.reset', [$passwordReset->token]);
        }
        catch (\Throwable $th) {
            return response()->json([
                'status'=>false,
                'message'=>$th->getMessage(),
            ], 500);
        }
    }

    /**
     * [checkEmail description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function checkEmail(Request $request)
    {
        try {
            $validateUser = Validator::make($request->all(),
            [
                'email' => 'required|string|email',
            ]);
            if ($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ], 401);
            }

            // check email
            $exist = User::where('email', $request->email)->whereIn('status', [User::PENDING, User::ACTIVE, User::ONBOARDING])->first();
            
            // email exist
            if ($exist) {
                return response()->json([
                    'status' => true,
                    'message' => 'Email is exist.',
                ]);
            }

            // email not exist
            return response()->json([
                'status' => true,
                'message' => 'Email is available.',
            ]);
        }
        catch (\Throwable $th) {
            return response()->json([
                'status'=>false,
                'message'=>$th->getMessage(),
            ], 500);
        }
    }

    public function register2(Request $request)
    {
        $escrowService = new \App\Services\PaymentGateway\FiuuPaymentService();
        // $result = $escrowService->getEscrowService(
        //     '31028590',
        // );
        // return response()->json($result);



        // $payeeData = [
        //     "Type"         => "Individual",
        //     "Full_Name"    => "John Doe",
        //     "NRIC_Passport"=> "900101015555",
        //     "ID_Type"      => "NRIC",
        //     "Country"      => "MY",
        //     "Bank_Name"    => "Maybank",
        //     "Bank_Code"    => "MBBEMYKL",
        //     "Bank_AccName" => "John Doe",
        //     "Bank_AccNumber" => "1122334455",
        //     "Email"        => "john@example.com",
        //     "Mobile"       => "60123456789",
        // ];
        // $result = $escrowService->getPayeeProfile($payeeData);
        // return response()->json($result);



        // $response = [
        //     'payeeId' => 541,
        //     'amount' => 250.75,
        //     'referenceId' => 'TXN_' . time(),
        //     'notifyUrl' => 'https://yourdomain.com/fiuu/payout-callback',
        // ];
        // $result = $escrowService->getPayeeStanding($response);
        // return response()->json($result);
    


        // $payeeData = [
        //     "Type"         => "Individual",
        //     "Full_Name"    => "John Doe",
        //     "NRIC_Passport"=> "900101015555",
        //     "ID_Type"      => "NRIC",
        //     "Country"      => "MY",
        //     "Bank_Name"    => "Maybank",
        //     "Bank_Code"    => "MBBEMYKL",
        //     "Bank_AccName" => "John Doe",
        //     "Bank_AccNumber" => "1122334455",
        //     "Email"        => "john@example.com",
        //     "Mobile"       => "60123456789",
        // ];
        // $amount = 250.76;
        // $referenceId = 'TXN_' . time();
        // $notifyUrl = 'https://yourdomain.com/fiuu/payout-callback';
        // $result = $escrowService->getDirectStanding($payeeData, $amount, $referenceId, $notifyUrl);
        // return response()->json($result);


        $amount = 250.76;
        $referenceId = 'TXN_' . time();
        $massId = 308;
        $result = $escrowService->getRequeryPayoutStanding($amount, $referenceId, $massId);
        return response()->json($result);

    }


}
