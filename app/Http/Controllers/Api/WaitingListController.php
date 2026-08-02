<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Postcode;
use App\Models\WaitingList;

class WaitingListController extends Controller
{
    /**
     * [joinWaitingList description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function joinWaitingList(Request $request)
    {
        try {
            $validateUser = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'mobile_no' => 'required|numeric|min:1',
                'postcode' => 'required|numeric',
            ]);

            if ($validateUser->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ]);
            }

            $postcode = Postcode::where('postcode', $request->postcode)->with(['city'])->first();
            if (!$postcode) {
                return response()->json([
                    'status' => false,
                    'message' => 'Postcode not exist.',
                ]);
            }

            $user = new WaitingList();
            $user->name = $request->name ?? null;
            $user->email = $request->email ?? null;
            $user->mobile_no = $request->mobile_no ?? null;
            $user->postcode = $request->postcode ?? null;
            $user->city_id = $postcode->city_id ?? null;
            $user->status = WaitingList::ACTIVE;
            $user->save();
            $user->load(['city.state']);

            return response()->json([
                'status' => true,
                'message' => 'Thank you! You are successfully added into waiting list.',
                'data' => $user,
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }
}
