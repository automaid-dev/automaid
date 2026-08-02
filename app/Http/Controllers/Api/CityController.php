<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CityController extends Controller
{
    /**
     * [listCity description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function listCity(Request $request)
    {
        try {
            // get user
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
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
     * [confirmCity description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function confirmCity(Request $request)
    {
        try {
            // get user
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }
            
            // validate input
            $validate = Validator::make($request->all(), [
                'checked_city' => 'required',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            if (isset($request->checked_city)) {
                $cities = json_decode($request->checked_city, true);

                // normalize values
                $cities = array_map('intval', $cities);

                // get existing city records keyed by city_id
                $existingCities = $user->covered_locations()
                    ->get()
                    ->keyBy('city_id');

                /*
                |--------------------------------------------------------------------------
                | 1. Set unchecked cities to inactive
                |--------------------------------------------------------------------------
                */
                $user->covered_locations()
                    ->whereNotIn('city_id', $cities)
                    ->update(['is_active' => false]);

                /*
                |--------------------------------------------------------------------------
                | 2. Loop checked cities
                |--------------------------------------------------------------------------
                */
                foreach ($cities as $cityId) {

                    if (isset($existingCities[$cityId])) {
                        // already exists → activate
                        $existingCities[$cityId]->update([
                            'is_active' => true,
                        ]);
                    } 
                    else {
                        // not exists → create new
                        $user->covered_locations()->create([
                            'city_id'    => $cityId,
                            'is_active'  => true,
                            'created_by' => $user->id,
                        ]);
                    }
                }
            }

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
}
