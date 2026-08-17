<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\AssignJob;
use App\Models\OrderStatus;
use App\Models\WashComplete;
use App\Models\Booking;
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
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }   

            // get status duty
            $data['is_duty'] = $user->is_duty;

            // get assigned jobs
            $codes = ['21', '22', '23', '24', '25', '26']; 

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
                    'status',
                    'order.qrcode_users.qrcode',
                ])
                ->get();

            // 🔥 helper logic
            $shouldShowPending = function ($job) {
                // code 21 → must be queued & not accepted
                if ($job->status->code == '21') {
                    return $job->is_queue && !$job->is_accepted;
                }

                // all other codes → only not accepted
                return !$job->is_accepted;
            };

            $data['assign_jobs'] = [

                // pending jobs - today
                'today' => $allJobs->filter(function ($job) use ($todayDate, $shouldShowPending) {
                    return $shouldShowPending($job)
                        && $job->booking->pickup_date == $todayDate;
                })->values(),

                // pending jobs - incoming
                'incoming' => $allJobs->filter(function ($job) use ($todayDate, $shouldShowPending) {
                    return $shouldShowPending($job)
                        && $job->booking->pickup_date > $todayDate;
                })->values(),
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
                        $q->where('code', '21')
                          ->where('is_queue', true)
                          ->where('is_accepted', false);
                    })
                    ->orWhere(function($q) {
                        // other codes → only is_accepted = false
                        $q->where('code', '!=', '21')
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
                        $q->where('code', '21')
                          ->where('is_queue', true)
                          ->where('is_accepted', false);
                    })
                    ->orWhere(function($q) {
                        // other codes → only is_accepted = false
                        $q->where('code', '!=', '21')
                          ->where('is_accepted', false);
                    });
                })
                ->groupBy('code')
                ->with(['status'])
                ->get();

            // get order histories
            // $orders = AssignJob::where(['code' => OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE, 'user_id' => $user->id, 'is_accepted' => true])->get();
            // $data['order_histories'] = $orders->load(['booking.all_order_status.status']);

            // wash completes
            $data['wash_completes'] = WashComplete::where(['created_by' => $user->id])->with(['order'])->get();

            return response()->json([
                'data' => $data,
                'status' => true,
                'message' => 'Dashboard merchant successfully retrieved.',
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
     * Every activity this merchant has been involved in — newest
     * first. Same reasoning as the matching rider endpoint: previously
     * no way to see what happened to a job after it left the active
     * dashboard, including an admin cancellation.
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

            // See the matching comment on Rider\HomeController's
            // version of this method — same fix, same reasoning.
            $orderIds = \App\Models\AssignJob::where('user_id', $user->id)
                ->where('is_accepted', true)
                ->distinct()
                ->pluck('order_id');

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

}
