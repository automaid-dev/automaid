<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;

class SettingController extends Controller
{
    /**
     * [setting description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function setting(Request $request)
    {
        try {
            $setting = Setting::find(1);
            return response()->json([
                'status' => true,
                'message' => 'Successfully retrieved setting.',
                'setting' => $setting,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }
}
