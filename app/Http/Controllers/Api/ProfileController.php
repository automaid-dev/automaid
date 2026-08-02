<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\MobileChange;
use App\Models\User;
use App\Models\State;
use App\Models\City;
use App\Services\OneWaySmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Seshac\Otp\Otp;

class ProfileController extends Controller
{
    /**
     * [logout description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function logout(Request $request)
    {
        // get current user
        $user = $request->user();

        // delete tokens
        $user->tokens()->delete();

        // set off duty
        $user->is_duty = false;

        // Set device_id to null and save
        $user->device_id = null;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Logout successfully.',
        ]);
    }
    
    /**
     * [me description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function me(Request $request)
    {
        try {
            return response()->json(auth()->user());
        } catch (\Throwable $th) {
            return response()->json([
                'status'=>false,
                'message'=>$th->getMessage(),
            ],500);
        }
    }

    /**
     * [token description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function token(Request $request)
    {
        try {
            return response()->json($request->bearerToken());
        } catch (\Throwable $th) {
            return response()->json([
                'status'=>false,
                'message'=>$th->getMessage(),
            ],500);
        }
    }

    /**
     * [updatePassword description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function updatePassword(Request $request)
    {
        try {
            $user = auth('sanctum')->user();            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }
            $validate = Validator::make($request->all(), [
                'password' => 'required|string|min:8|confirmed',
                'password_confirmation' => 'required|min:8'  
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            if ((Hash::check($request->password, $user->password)) == true) {
                return response()->json([
                    'status' => false,
                    'message' => 'Please enter a password which is not similar then current password.',
                ]);
            } 
            else {
                $user->password = Hash::make($request->password);
                $user->save();
                return response()->json([
                    'status' => true,
                    'message' => 'Password successfully updated.',
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
     * [activityDetail description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function activityDetail(Request $request)
    {
        try {
            $user = auth('sanctum')->user();            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }
            $validate = Validator::make($request->all(), [
                'activity_id' => 'required',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }
            $activity = Activity::where(['id' => $request->activity_id, 'user_id' => $user->id])->first();
            if (!$activity) {
                return response()->json([
                    'status' => false,
                    'message' => 'Activity is not found.',
                ]); 
            }
            return response()->json([
                'status' => true,
                'message' => 'Activity retrieved successfully.',
                'data' => $activity
            ]);  
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }
 

    /**
     * [saveDevice description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function saveDevice(Request $request)
    {
        try {
            // check user
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }
            $validate = Validator::make($request->all(), [
                'device_id' => 'required',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            // update device_id
            $user->device_id = $request->device_id;
            $user->save();

            // return data
            return response()->json([
                'status' => true,
                'message' => 'Device ID successfully updated.',
            ]);  
            
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [mobileUpdate description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function mobileUpdate(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }       

            // check input     
            $validateUser = Validator::make($request->all(), [
                'mobile_no' => 'required|numeric|min:1',
            ]);
            if ($validateUser->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ]);
            }

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
            MobileChange::updateOrCreate([
                'user_id' => $user->id,
                'new_mobile' => $request->mobile_no
            ]);

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
     * [mobileVerify description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function mobileVerify(Request $request)
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
                'message' => 'Thank you! Your mobile number has been successfully updated.',
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
     * [iAgree description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function iAgree(Request $request)
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

            // not yet accepted term & condition
            if (!$user->is_agree) {
                
                // check input     
                $validateUser = Validator::make($request->all(), [
                    'i_agree' => 'accepted',
                ]);
                if ($validateUser->fails()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'validation error',
                        'errors' => $validateUser->errors()
                    ]);
                }

                // update i agree
                $user->is_agree = true;
                $user->updated_at = now();
                $user->updated_by = $user->id;
                $user->save();

                // return data
                return response()->json([
                    'status' => true,
                    'message' => 'Accepted Term & Conditions. You may proceed to Home screen.',
                ]);
            }

            // already accepted term & condition
            else {
                return response()->json([
                    'status' => true,
                    'message' => 'Already Accepted Term & Conditions.',
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
     * [locationIndex description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function locationIndex(Request $request)
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

            // check covered locations
            if (count($user->covered_locations) == 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Covered location is empty.',
                ]); 
            }

            // get covered locations
            $covered_locations = $user->covered_locations->load('city.state'); 
            $data['covered_locations'] = $covered_locations;
            return response()->json([
                'status' => true,
                'data' => $data,
                'message' => 'Successfully retrieved covered locations.',
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [locationList description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function locationList(Request $request)
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

            // Fetch states with cities
            $states_cities = State::with(['cities' => function($query) {
                $query->select('id', 'name', 'state_id')->orderBy('name', 'asc');
            }])
            ->select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

            return response()->json([
                'status' => true,
                'data' => $states_cities,
                'message' => 'Successfully retrieved states and cities.',
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [locationUpdate description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function locationUpdate(Request $request)
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

            // Validate input
            $request->validate([
                'checked_city' => 'required',
            ]);

            if (isset($request->checked_city)) {
                $cities = json_decode($request->checked_city, true);

                // normalize values (important)
                $cities = array_map('intval', $cities);

                // get existing city IDs from DB
                $existingCityIds = $user->covered_locations()
                    ->pluck('city_id')
                    ->toArray();

                // delete unchecked cities
                $user->covered_locations()
                    ->whereNotIn('city_id', $cities)
                    ->delete();

                // add newly checked cities
                $newCityIds = array_diff($cities, $existingCityIds);
                if (!empty($newCityIds)) {
                    $data = collect($newCityIds)->map(function ($cityId) use ($user) {
                        return [
                            'city_id'     => $cityId,
                            'created_by'  => $user->id,
                        ];
                    })->toArray();
                    $user->covered_locations()->createMany($data);
                }
            }

            // update data
            return response()->json([
                'status' => true,
                'message' => 'Cities updated successfully.',
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [locationDelete description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function locationDelete(Request $request)
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

            // check input     
            $validateUser = Validator::make($request->all(), [
                'city_id' => 'required',
            ]);
            if ($validateUser->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ]);
            }

            $city = City::find($request->city_id);
            if (!$city) {
                return response()->json([
                    'status' => false,
                    'message' => 'City not found.',
                ]);  
            }

            // Check city belongs to this user
            $coveredLocation = $user->covered_locations()
                ->where('city_id', $city->id)
                ->first();

            if (!$coveredLocation) {
                return response()->json([
                    'status' => false,
                    'message' => 'City is not assigned to this user.',
                ]);
            }

            // Delete relationship (NOT the city)
            $coveredLocation->delete();

            return response()->json([
                'status' => true,
                'message' => 'City removed successfully.',
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }
}
