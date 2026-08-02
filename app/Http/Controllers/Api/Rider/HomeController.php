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


}
