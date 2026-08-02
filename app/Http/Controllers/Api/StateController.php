<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\State;

class StateController extends Controller
{
    /**
     * [index description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function index(Request $request)
    {
        try {
            $data['states'] = State::orderBy('name', 'asc')->get();
            return response()->json([
                'status' => true,
                'message' => 'Successfully retrieved states.',
                'data' => $data,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }
}
