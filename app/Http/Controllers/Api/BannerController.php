<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BannerController extends Controller
{
    /**
     * Returns active banners for the requesting app's dashboard,
     * ordered for display. One shared endpoint for all three roles
     * (customer, merchant, rider) rather than three near-identical
     * copies — which banners come back depends entirely on `target`,
     * not on the caller's role.
     */
    public function index(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'target' => 'required|in:' . Banner::TARGET_CUSTOMER . ',' . Banner::TARGET_MERCHANTRIDER,
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            $banners = Banner::active()
                ->forTarget($request->target)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Successfully retrieved banners.',
                'data' => ['banners' => $banners],
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
