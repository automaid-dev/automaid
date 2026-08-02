<?php

namespace App\Http\Controllers\Api\Merchant;

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
                'name' => 'required|string|max:255',
                'icno' => 'required',
                'id_type' => 'required',

                'country_name' => 'required',
                'state_name' => 'required',
                'postcode' => 'required',
                'city' => 'required',

                'washer_quantity' => 'required|numeric',  
                'dryer_quantity' => 'required|numeric',
                'ic_front' => 'image|mimes:jpg,png|max:10240',
                'ic_back' => 'image|mimes:jpg,png|max:10240',
                'ssm_cert' => 'image|mimes:jpg,png|max:10240',
                'ssm_no' => 'required',

                'company_name' => 'required',                
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

            // get merchant info
            if (!$user->merchant) {
                return response()->json([
                    'status' => false,
                    'message' => 'Merchant is not exist.',
                ]); 
            }
            $merchant = $user->merchant;

            $ic_front = $merchant->ic_front;
            if (isset($request->ic_front) && $request->ic_front !== null) {          
                $ic_front = $this->uploadFile($request->ic_front);
            }

            $ic_back = $merchant->ic_back;
            if (isset($request->ic_back) && $request->ic_back !== null) {
                $ic_back = $this->uploadFile($request->ic_back);
            }

            $ssm_cert = $merchant->ssm_cert;
            if (isset($request->ssm_cert) && $request->ssm_cert !== null) {
                $ssm_cert = $this->uploadFile($request->ssm_cert);
            }

            // update user info
            $user->name = $request->name ?? null;
            $user->id_type = $request->id_type ?? null;
            $user->icno = $request->icno ?? null;

            // update address
            $user->unit_no = $request->unit_no ?? null;
            $user->block = $request->block ?? null;
            $user->address_line_1 = $request->address_line_1 ?? null;
            $user->address_line_2 = $request->address_line_2 ?? null;
            $user->country_id = $request->country_name ? get_country_id($request->country_name)['id'] : null;
            $user->state_id = $request->state_name ? get_state_id($request->state_name)['id'] : null;
            $user->postcode = $request->postcode ?? null;
            $user->city = $request->city ?? null;
            $user->status = User::ONBOARDING;
            $user->save();

            // update merchant address
            $merchant->unit_no = $request->unit_no ?? null;
            $merchant->block = $request->block ?? null;
            $merchant->address_line_1 = $request->address_line_1 ?? null;
            $merchant->address_line_2 = $request->address_line_2 ?? null;
            $merchant->country_id = $request->country_name ? get_country_id($request->country_name)['id'] : null;
            $merchant->state_id = $request->state_name ? get_state_id($request->state_name)['id'] : null;
            $merchant->postcode = $request->postcode ?? null;
            $merchant->city = $request->city ?? null;

            // update laundry equipment
            $merchant->washer_quantity = $request->washer_quantity ?? null;
            $merchant->dryer_quantity = $request->dryer_quantity ?? null;
            $merchant->service_categories = $request->service_categories ?? null;

            // update company info
            $merchant->company_name = $request->company_name ?? null;
            $merchant->ssm_no = $request->ssm_no ?? null;
            $merchant->business_option = $request->business_option ?? null;

            // update bank info
            $merchant->bank_name = $request->bank_name ?? null;
            $merchant->bank_no = $request->bank_no ?? null;

            // update merchant verification
            $merchant->ic_front = $ic_front;
            $merchant->ic_back = $ic_back;
            $merchant->ssm_cert = $ssm_cert;
            $merchant->save();

            // return success
            return response()->json([
                'status' => true,
                'message' => 'Merchant successfully re-apply.',
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
}
