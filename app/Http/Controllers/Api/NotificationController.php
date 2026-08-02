<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * [index description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function index(Request $request)
    {
        try {
            $notifications = auth()->user()->notifications;
            return response()->json([
                'status' => true,
                'message' => 'Notifications retrieved successfully',
                'data' => $notifications
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * [index description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function unread(Request $request)
    {
        try {
            $notifications = auth()->user()->unreadNotifications;
            return response()->json([
                'status' => true,
                'message' => 'Notifications retrieved successfully',
                'data' => $notifications
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * [read description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function read(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required',
            ]);
            $id = $request->id;
            $notification = auth()->user()->unreadNotifications->where('id', $id)->first();
            if ($notification) {
                $notification->markAsRead();
            }
            return response()->json([
                'status' => true,
                'message' => 'Notification successfully read',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * [read_all description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function read_all(Request $request)
    {
        try {
            auth()->user()->unreadNotifications->markAsRead();
            return response()->json([
                'status' => true,
                'message' => 'All Notifications marked read',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * [delete description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function delete(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required',
            ]);
            $id = $request->id;
            // Use the correct method to get notifications
            $notification = auth()->user()->notifications()->where('id', $id)->first();

            if ($notification) {
                $notification->delete();
                return response()->json([
                    'status' => true,
                    'message' => 'Notification successfully deleted!'
                ], 200);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Notification not found.'
                ], 404);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
