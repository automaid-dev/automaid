<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Lists the logged-in customer's notifications, newest first.
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function index(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);
            }

            $notifications = Notification::where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit(100)
                ->get();

            $unreadCount = Notification::where('user_id', $user->id)
                ->whereNull('read_at')
                ->count();

            return response()->json([
                'status' => true,
                'data' => [
                    'notifications' => $notifications,
                    'unread_count' => $unreadCount,
                ],
                'message' => 'Successfully retrieved notifications.',
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Marks one notification (by id) as read, or every unread
     * notification if no id is given.
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function markRead(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);
            }

            $query = Notification::where('user_id', $user->id)->whereNull('read_at');
            if ($request->id) {
                $query->where('id', $request->id);
            }
            $query->update(['read_at' => now()]);

            return response()->json([
                'status' => true,
                'message' => 'Marked as read.',
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
