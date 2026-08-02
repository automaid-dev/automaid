<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Announcement;

class AnnouncementController extends Controller
{
    /**
     * [announcements description]
     * @return [type] [description]
     */
    public function announcements()
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }

            // get announcements
            $announcements = Announcement::published()->latest()->get();

            // return announcements
            $data['announcements'] = $announcements;            
            return response()->json([
                'data' => $data,
                'status' => true,
                'message' => 'Announcements successfully retrieved.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

}
