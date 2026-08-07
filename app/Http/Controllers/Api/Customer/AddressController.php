<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Address;

class AddressController extends Controller
{
    /**
     * [saveAddress description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function saveAddress(Request $request)
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
            $validate = Validator::make($request->all(), [
                // 'unit_no' => 'required',
                'address_line_1' => 'required',
                'postcode' => 'required',
                'city' => 'required',
                'state_name' => 'required',
                'country_name' => 'required',
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

            // resolve country/state — these are matched by name from
            // free-text mobile app input, so a typo or unrecognised name
            // is a real (if unlikely) possibility; fail cleanly instead of
            // saving an address with a broken country_id/state_id.
            $country = $request->country_name ? get_country_id($request->country_name) : null;
            $state = $request->state_name ? get_state_id($request->state_name) : null;
            if ($request->country_name && !$country) {
                return response()->json([
                    'status' => false,
                    'message' => "Country \"{$request->country_name}\" is not recognised.",
                ]);
            }
            if ($request->state_name && !$state) {
                return response()->json([
                    'status' => false,
                    'message' => "State \"{$request->state_name}\" is not recognised.",
                ]);
            }

            // insert new address
            $address = new Address();
            $address->user_id = $user->id;
            $address->unit_no = $request->unit_no ?? null;
            $address->floor = $request->floor ?? null;
            $address->block = $request->block ?? null;
            $address->address_line_1 = $request->address_line_1 ?? null;
            $address->address_line_2 = $request->address_line_2 ?? null;
            $address->country_id = $country['id'] ?? null;
            $address->state_id = $state['id'] ?? null;
            $address->postcode = $request->postcode ?? null;
            $address->city = $request->city ?? null;
            $address->address_title = $request->address_title ?? null;
            $address->latitude = $request->latitude ?? null;
            $address->longitude = $request->longitude ?? null;
            $address->status = Address::ACTIVE;
            $address->save();

            $address->load(['state', 'country']);
            $data['address'] = $address;
            return response()->json([
                'status' => true,
                'message' => 'Address successfully added.',
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
     * [updateAddress description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function updateAddress(Request $request)
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

            // check input data
            $validate = Validator::make($request->all(), [
                'address_id' => 'required',
                // 'unit_no' => 'required',
                'address_line_1' => 'required',
                'postcode' => 'required',
                'city' => 'required',
                'state_name' => 'required',
                'country_name' => 'required',
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

            // check address
            $address = Address::where('id', $request->address_id)->active()->first();
            if (!$address) {
                return response()->json([
                    'status' => false,
                    'message' => 'Address not found.',
                ]);  
            }

            // resolve country/state — see saveAddress() for why this is
            // checked explicitly rather than trusting the helper alone.
            $country = $request->country_name ? get_country_id($request->country_name) : null;
            $state = $request->state_name ? get_state_id($request->state_name) : null;
            if ($request->country_name && !$country) {
                return response()->json([
                    'status' => false,
                    'message' => "Country \"{$request->country_name}\" is not recognised.",
                ]);
            }
            if ($request->state_name && !$state) {
                return response()->json([
                    'status' => false,
                    'message' => "State \"{$request->state_name}\" is not recognised.",
                ]);
            }

            // update address
            $address->unit_no = $request->unit_no ?? null;
            $address->floor = $request->floor ?? null;
            $address->block = $request->block ?? null;
            $address->address_line_1 = $request->address_line_1 ?? null;
            $address->address_line_2 = $request->address_line_2 ?? null;
            $address->country_id = $country['id'] ?? null;
            $address->state_id = $state['id'] ?? null;
            $address->postcode = $request->postcode ?? null;
            $address->city = $request->city ?? null;
            $address->address_title = $request->address_title ?? null;
            $address->latitude = $request->latitude ?? null;
            $address->longitude = $request->longitude ?? null;
            $address->save();

            // return address data
            $address->load(['state', 'country']);
            $data['address'] = $address;
            return response()->json([
                'status' => true,
                'message' => 'Address successfully updated.',
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
     * [deleteAddress description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function deleteAddress(Request $request)
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
            $validate = Validator::make($request->all(), [
                'address_id' => 'required',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            // check address
            $address = Address::where(['user_id' => $user->id, 'id' => $request->address_id])->first();
            if (!$address) {
                return response()->json([
                    'status' => false,
                    'message' => 'Address not exist.',
                ]);  
            }

            // delete address
            $address->delete();

            return response()->json([
                'status' => true,
                'message' => 'Address successfully deleted.',  
                'data' => null,              
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

}
