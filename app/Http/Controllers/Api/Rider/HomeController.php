<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\AssignJob;
use App\Models\Order;
use App\Models\Booking;
use App\Models\OrderStatus;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HomeController extends Controller
{
    /**
     * [home description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function home(Request $request)
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

            // get status duty
            $data['is_duty'] = $user->is_duty;

            // allowed status codes
            $codes = ['11', '12', '13', '14', '15', '16', '17'];

            // date today
            $todayDate = now()->toDateString();

            // fetch jobs
            $allJobs = AssignJob::where('user_id', $user->id)
                ->whereHas('status', function ($query) use ($codes) {
                    $query->whereIn('code', $codes);
                })
                ->whereHas('booking', function ($query) {
                    $query->where('status', '!=', Booking::CANCEL);
                })
                ->with([
                    'order.booking.pickup_location',
                    'order.user.bag_purchases',
                    'order.user.qrcodes',
                    'order.order_addons.addon',
                    'order.merchant.user.merchant.outlet',
                    'status',
                    'order.qrcode_users.qrcode',
                ])
                ->latest()
                ->get();

            // 🔥 helper logic
            $shouldShowPending = function ($job) {
                // code 11 → must be queued & not accepted
                if ($job->status->code == '11') {
                    return $job->is_queue && !$job->is_accepted;
                }

                // all other codes → only not accepted
                return !$job->is_accepted;
            };

            $data['assign_jobs'] = [

                // pending jobs - today (includes anything overdue —
                // previously this only matched pickup_date == today
                // exactly, so a still-pending job whose pickup date had
                // already passed matched neither this nor the
                // "incoming" filter below and simply vanished from the
                // dashboard entirely, with no way for the rider to see
                // or act on it)
                'today' => $allJobs->filter(function ($job) use ($todayDate, $shouldShowPending) {
                    return $shouldShowPending($job)
                        && $job->booking->pickup_date <= $todayDate;
                })->values(),

                // pending jobs - incoming
                'incoming' => $allJobs->filter(function ($job) use ($todayDate, $shouldShowPending) {
                    return $shouldShowPending($job)
                        && $job->booking->pickup_date > $todayDate;
                })->values(),

                // Active bookings — accepted (is_accepted=true) but not
                // yet at the final delivered stage (code 16 accepted).
                // Previously the dashboard only ever showed pending
                // (not-yet-accepted) jobs, so the moment a rider
                // accepted a job it vanished from their own dashboard
                // entirely until they went looking in activity history
                // — which, on top of that, mixed together completed AND
                // still-in-progress orders with no way to tell them
                // apart. One row per order (its furthest-progressed
                // job, by creation order — NOT by numeric code, since
                // code 17 "awaiting wash to complete" is numerically
                // higher than 16 but conceptually sits mid-flow, so
                // sorting by code would misorder it as if it were
                // further along than it actually is) so an order mid-
                // flow across several codes shows once, not once per
                // stage.
                'active' => $allJobs
                    ->whereNotIn('order_id', $allJobs->filter(function ($job) {
                        return $job->status->code == \App\Models\OrderStatus::RIDER_ORDER_DELIVERED && $job->is_accepted;
                    })->pluck('order_id')->all())
                    ->groupBy('order_id')
                    ->map(function ($jobs) {
                        return $jobs->sortByDesc('id')->first();
                    })
                    ->values(),
            ];

            // get total assigned jobs today
            $data['total_assign_jobs']['today'] = AssignJob::select('code', DB::raw('COUNT(*) as count'))
                ->where('user_id', $user->id)
                ->whereHas('status', function($query) use ($codes) {
                    $query->whereIn('code', $codes);
                })
                ->whereHas('booking', function($query) {
                    $query->whereDate('pickup_date', now()->format('Y-m-d'))
                          ->where('status', '!=', Booking::CANCEL);                
                })
                ->where(function($query) {
                    // conditional logic
                    $query->where(function($q) {
                        // code 11 → is_queue = true AND is_accepted = false
                        $q->where('code', '11')
                          ->where('is_queue', true)
                          ->where('is_accepted', false);
                    })
                    ->orWhere(function($q) {
                        // other codes → only is_accepted = false
                        $q->where('code', '!=', '11')
                          ->where('is_accepted', false);
                    });
                })
                ->groupBy('code')
                ->with(['status'])
                ->get();

            // get total assigned jobs incoming
            $data['total_assign_jobs']['incoming'] = AssignJob::select('code', DB::raw('COUNT(*) as count'))
                ->where('user_id', $user->id)
                ->whereHas('status', function($query) use ($codes) {
                    $query->whereIn('code', $codes);
                })
                ->whereHas('booking', function($query) {
                    $query->whereDate('pickup_date', '>', now()->format('Y-m-d'))
                          ->where('status', '!=', Booking::CANCEL);                
                })
                ->where(function($query) {
                    // conditional logic
                    $query->where(function($q) {
                        // code 11 → is_queue = true AND is_accepted = false
                        $q->where('code', '11')
                          ->where('is_queue', true)
                          ->where('is_accepted', false);
                    })
                    ->orWhere(function($q) {
                        // other codes → only is_accepted = false
                        $q->where('code', '!=', '11')
                          ->where('is_accepted', false);
                    });
                })
                ->groupBy('code')
                ->with(['status'])
                ->get();

            return response()->json([
                'data' => $data,
                'status' => true,
                'message' => 'Dashboard rider successfully retrieved.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [updateDuty description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function updateDuty(Request $request)
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

            // check input
            $validate = Validator::make($request->all(), [
                'is_duty' => 'required',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            // update status duty
            $user->is_duty = $request->is_duty;
            $user->updated_by = $user->id;
            $user->save();

            // return data user
            return response()->json([
                'data' => $user,
                'status' => true,
                'message' => 'Status duty successfully  updated.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * Every activity (accepted, delivered, cancelled by admin, etc.)
     * this rider has been involved in — newest first. Didn't exist
     * before; the rider app only ever showed today's/incoming active
     * jobs, with no way to see what happened to a job afterward
     * (including an admin cancellation, which just made the job
     * disappear with no explanation).
     *
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function activityHistory(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);
            }

            // Every order this rider has ever accepted, at any stage —
            // not just ones that reached final delivery. The previous
            // version of this query relied on the Activity model, which
            // only gets a row written at final delivery confirmation or
            // an admin cancellation — an order the rider accepted and is
            // still actively working never showed up here at all, which
            // is why this screen could show "No activity yet" even with
            // real order history behind it.
            $orderIds = \App\Models\AssignJob::where('user_id', $user->id)
                ->where('is_accepted', true)
                ->distinct()
                ->pluck('order_id');

            // Exclude orders still actively in progress — those belong
            // on the dashboard's new "Active booking" section, not
            // history. Without this, an order showed in BOTH places
            // simultaneously the moment it was accepted, with no way
            // to tell from history alone whether it was actually done.
            $completedOrEndedOrderIds = \App\Models\AssignJob::where('user_id', $user->id)
                ->where('code', \App\Models\OrderStatus::RIDER_ORDER_DELIVERED)
                ->where('is_accepted', true)
                ->distinct()
                ->pluck('order_id');
            $orderIds = $orderIds->intersect($completedOrEndedOrderIds->merge(
                \App\Models\Order::whereIn('id', $orderIds)
                    ->whereHas('booking', function ($q) {
                        $q->where('status', \App\Models\Booking::CANCEL);
                    })
                    ->pluck('id')
            ));

            $orders = \App\Models\Order::whereIn('id', $orderIds)
                ->with(['booking', 'delivered'])
                ->orderByDesc('id')
                ->limit(100)
                ->get();

            return response()->json([
                'status' => true,
                'data' => ['orders' => $orders],
                'message' => 'Successfully retrieved order history.',
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * The rider's own in-app notifications — reads from Laravel's
     * native notifications table (via the Notifiable trait's
     * ->notifications relation), which is where every
     * CustomerReadyPickup / MerchantDeliveryWashOutlet / etc.
     * notification's toArray() output actually gets saved. Previously
     * there was no endpoint for this at all — the notification bell on
     * this dashboard just linked to order history instead, since a
     * real notifications screen was never built.
     */
    public function notifications(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);
            }

            $notifications = $user->notifications()->limit(100)->get();
            $unreadCount = $user->unreadNotifications()->count();

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
            ],500);
        }
    }

    /**
     * Marks all of the rider's notifications as read — called when
     * they open the notifications screen, same pattern as the customer
     * app.
     */
    public function markNotificationsRead(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);
            }

            $user->unreadNotifications()->update(['read_at' => now()]);

            return response()->json([
                'status' => true,
                'message' => 'Notifications marked as read.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

}
