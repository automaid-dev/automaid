<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Outlet;
use Illuminate\Support\Facades\Validator;

class LocationController extends Controller
{
    /**
     * [searchLocation description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function searchLocation(Request $request)
    {
        try {
            // $validateLocation = Validator::make($request->all(), [
            //     'name' => 'required',               
            // ]);
            // if ($validateLocation->fails()) {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'validation error',
            //         'errors' => $validateLocation->errors()
            //     ]);
            // }

            $name = $request->name ?? null;
            $postcode = $request->postcode ?? null;
            $lat = $request->lat ?? null;
            $long = $request->long ?? null;

            $locations = Outlet::orderBy('name', 'asc')
            ->when($name, function($query) use ($name) {
                $query->where('name', 'like', '%' . $name . '%');
            })
            ->when($postcode, function($query) use ($postcode) {
                $query->where('postcode', $postcode);
            })
            ->when($lat, function($query) use ($lat) {
                $query->where('lat', $lat);
            })
            ->when($long, function($query) use ($long) {
                $query->where('long', $long);
            })
            ->get();

            return response()->json([
                'status' => true,
                'message' => 'Locations retrieved successfully.',
                'locations' => $locations,
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [checkLocation description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function checkLocation(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'city' => 'required',               
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            $city = $request->city ?? null;
            $outlet = Outlet::orderBy('name', 'asc')
            ->when($city, function($query) use ($city) {
                $query->where('city', $city);
            })
            ->first();

            if (!$outlet) {
                return response()->json([
                    'status' => false,
                    'message' => 'City not found.',
                ]);
            }
            return response()->json([
                'status' => true,
                'message' => 'City is available.',
                'outlet' => $outlet,
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }
}
