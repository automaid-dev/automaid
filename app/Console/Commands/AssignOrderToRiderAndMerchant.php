<?php

namespace App\Console\Commands;

use App\Events\MerchantPendingAcceptance;
use App\Events\RiderPendingAcceptance;
use App\Models\AssignJob;
use App\Models\AssignJobQueue;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Rider;
use App\Models\Setting;
use App\Models\User;
use App\Services\OneSignalService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignOrderToRiderAndMerchant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automaid:assign-order-to-rider-and-merchant';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * [calculateDistanceInMeters description]
     * @param  [type] $lat1 [description]
     * @param  [type] $lon1 [description]
     * @param  [type] $lat2 [description]
     * @param  [type] $lon2 [description]
     * @return [type]       [description]
     */
    public function calculateDistanceInMeters($lat1, $lon1, $lat2, $lon2)
    {
        // Earth's radius in meters
        $earthRadius = 6371000;

        // Convert degrees to radians
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        // Calculate differences
        $dLat = $lat2 - $lat1;
        $dLon = $lon2 - $lon1;

        // Haversine formula
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos($lat1) * cos($lat2) * 
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        // Distance in meters
        $distance = $earthRadius * $c;

        return round($distance, 2);
    }

    /**
     * [calculateMaxBags description]
     * @param  [type] $w [description]
     * @param  [type] $d [description]
     * @return [type]    [description]
     */
    public function calculateMaxBags($w, $d)
    {
        $bag_weight = 7;                    // kg per laundry bag
        $washing_machine_capacity = 14;     // kg per load for washing machine
        $dryer_capacity = 10;               // kg per load for dryer
        $operation_hours = 6;               // hours per day
        $cycle_time = 1;                    // hours per load (same for washing and drying)

        // Calculate total loads per machine type per day
        $washing_loads_per_day = ($operation_hours / $cycle_time) * $w;
        $drying_loads_per_day = ($operation_hours / $cycle_time) * $d;

        // Calculate total capacities per day
        $total_washing_capacity_per_day = $washing_loads_per_day * $washing_machine_capacity;
        $total_drying_capacity_per_day = $drying_loads_per_day * $dryer_capacity;

        // Find the limiting factor and determine max bags
        $limiting_capacity = min($total_washing_capacity_per_day, $total_drying_capacity_per_day);
        $max_bags = floor($limiting_capacity / $bag_weight); // Return whole number of bags

        return $max_bags;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // get orders with status pending acceptance
        $orders = Order::whereHas('order_statuses', function ($q) {
                $q->whereIn('code', ['11', '21'])
                  ->where('is_done', false);
            })
            ->with(['order_statuses' => function ($q) {
                $q->whereIn('code', ['11', '21'])
                  ->where('is_done', false);
            }])
            ->orderBy('id', 'asc')
            ->get();

        if (count($orders) > 0) {
            foreach ($orders as $order) {
                try {

                $total_riders = 0;
                $total_merchants = 0;
                $first_check = false;

                // check order info
                $location = $order->booking->pickup_location;
                $date = Carbon::parse($order->created_at)->format('Y-m-d');
                $city_name = $order->billing_city;

                // get order status
                $pending_status = $order->order_statuses;
                if (count($pending_status) > 0) {
                    foreach ($pending_status as $status) {
                    try {

                        // has order
                        if ($location && $date && $city_name) {

                            // check if status code for rider
                            if ($status->code == OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE) {

                                // check 1 assign job queue
                                $queue = $order->assign_job_queue;

                                // have queue & status pending
                                if ($queue && $queue->user_type == User::RIDER) {

                                    // check if now is past or equal to trigger time
                                    $created_at = $queue->created_at->copy()->second(0);
                                    $trigger_at = $created_at->addMinutes($queue->time_interval);
                                    if ($trigger_at->lte(now())) {

                                        // get user info
                                        $user = $queue->user;
                                        if ($user) {

                                            // get rider info
                                            $rider = $queue->user->rider;
                                            if ($rider) {

                                                // check if rider have assigned job
                                                $assigned = AssignJob::where([
                                                    'code' => OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE,
                                                    'user_id' => $user->id, 
                                                    'order_id' => $order->id,
                                                ])->first();

                                                // not assign yet
                                                if (!$assigned) {

                                                    // vehicle type is motorcycle
                                                    if (strtolower($rider->type_vehicle) == Rider::MOTORCYCLE) {

                                                        // check taken job rider on order created
                                                        $taken = AssignJob::where([
                                                            'accepted_by' => $user->id,
                                                            'is_accepted' => true,
                                                        ])
                                                        ->where('code', OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE)
                                                        ->whereDate('accepted_at', $date)
                                                        ->count();

                                                        // check if taken job not 3 yet
                                                        if ($taken <= 3) {

                                                            // set previous assignee queue false
                                                            AssignJob::where([
                                                                'code' => OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE,
                                                                'order_id' => $order->id,
                                                            ])->update(['is_queue' => false]);

                                                            // update queue status
                                                            $queue->status = AssignJobQueue::QUEUED;
                                                            $queue->save();

                                                            // assign job to rider
                                                            $job = new AssignJob();
                                                            $job->code = OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE;
                                                            $job->user_id = $user->id;
                                                            $job->order_id = $status->order_id;
                                                            $job->order_status_id = $status->id;
                                                            $job->is_queue = true;
                                                            $job->save();

                                                            // send pn to rider
                                                            event(new RiderPendingAcceptance($user, $job));                                                                                       
                                                        }
                                                    }

                                                    // other vehicle type
                                                    else {

                                                        // set previous assignee queue false
                                                        AssignJob::where([
                                                            'code' => OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE,
                                                            'order_id' => $order->id,
                                                        ])->update(['is_queue' => false]);                                        

                                                        // update queue status
                                                        $queue->status = AssignJobQueue::QUEUED;
                                                        $queue->save();

                                                        // assign job to rider
                                                        $job = new AssignJob();
                                                        $job->code = OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE;
                                                        $job->user_id = $user->id;
                                                        $job->order_id = $status->order_id;
                                                        $job->order_status_id = $status->id;
                                                        $job->is_queue = true;
                                                        $job->save();

                                                        // send pn to rider
                                                        event(new RiderPendingAcceptance($user, $job));                                                   
                                                    }
                                                }
                                            } 
                                        }
                                    }
                                }

                                // queue not found
                                else {

                                    // check if trigger 1st cronjob
                                    if (!$status->is_check_queue) {

                                        $first_check = true;

                                        // get nearby rider
                                        $users = User::role('rider')
                                            ->has('rider') 
                                            ->whereHas('covered_locations', function($q) use ($city_name) {
                                                $q->where('is_active', true);
                                                $q->whereHas('city', function ($c) use ($city_name) {
                                                    $c->where('name', $city_name);
                                                });
                                            })
                                            ->where('is_duty', true)
                                            ->whereNotNull('latitude')
                                            ->whereNotNull('longitude')
                                            ->active()
                                            ->get();

                                        // Track how many candidates were actually
                                        // found — this was previously never set
                                        // anywhere in this file (stuck at its
                                        // initial 0), which made the "no riders
                                        // found" branch further down fire on every
                                        // first attempt regardless of whether
                                        // riders genuinely existed nearby.
                                        $total_riders = count($users);

                                        // found assigned riders
                                        if (count($users) > 0) {

                                            $user = null;
                                            $coordinate = [];
                                            foreach ($users as $user) {

                                                // get rider info
                                                $rider = $user->rider;
                                                if ($rider) {

                                                    // check if rider have assigned job
                                                    $assigned = AssignJob::where([
                                                        'code' => OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE,
                                                        'user_id' => $user->id, 
                                                        'order_id' => $order->id,
                                                    ])->first();

                                                    // not assign yet
                                                    if (!$assigned) {

                                                        // vehicle type is motorcycle
                                                        if (strtolower($rider->type_vehicle) == Rider::MOTORCYCLE) {

                                                            // check taken job rider on order created
                                                            $taken = AssignJob::where([
                                                                'accepted_by' => $user->id,
                                                                'is_accepted' => true,
                                                            ])
                                                            ->where('code', OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE)
                                                            ->whereDate('accepted_at', $date)
                                                            ->count();

                                                            // check if taken job not 3 yet
                                                            if ($taken <= 3) {

                                                                // check coordinate
                                                                $coordinate[$user->id] = $this->calculateDistanceInMeters(
                                                                    $location['latitude'], 
                                                                    $location['longitude'], 
                                                                    $user->latitude, 
                                                                    $user->longitude
                                                                );                                                                                         
                                                            }
                                                        }

                                                        // other vehicle type
                                                        else {

                                                            // check coordinate
                                                            $coordinate[$user->id] = $this->calculateDistanceInMeters(
                                                                $location['latitude'], 
                                                                $location['longitude'], 
                                                                $user->latitude, 
                                                                $user->longitude
                                                            );                                                  
                                                        }
                                                    }
                                                }
                                            }

                                            // have coordinates
                                            if (count($coordinate) > 0) {

                                                // check the 4 nearest 
                                                $nearests = collect($coordinate)
                                                    ->sort()  
                                                    ->take(4);

                                                // check the nearest 
                                                $nearest = collect($coordinate)
                                                    ->sort()
                                                    ->keys()
                                                    ->first();

                                                // insert queue
                                                $queueOrder = 1;
                                                $interval = 0;
                                                foreach ($nearests as $user_id => $distance) {
                                                    $interval += 10;
                                                    AssignJobQueue::updateOrCreate(
                                                        [
                                                            'order_id' => $status->order_id,
                                                            'user_id'  => $user_id,
                                                        ],
                                                        [
                                                            'user_type' => User::RIDER,
                                                            'distance' => $distance,
                                                            'status'   => ($nearest == $user_id)
                                                                ? AssignJobQueue::QUEUED
                                                                : AssignJobQueue::PENDING,
                                                            'queue_position' => $queueOrder,
                                                            'time_interval' => $interval,
                                                        ]
                                                    );
                                                    $queueOrder++;
                                                    $total_riders++;                                                    
                                                }

                                                // assign nearest rider
                                                $user = User::find($nearest);      
                                                if ($user) {

                                                    // assign job to rider
                                                    $job = new AssignJob();
                                                    $job->code = OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE;
                                                    $job->user_id = $user->id;
                                                    $job->order_id = $status->order_id;
                                                    $job->order_status_id = $status->id;
                                                    $job->is_queue = true;
                                                    $job->save();

                                                    // send pn to rider
                                                    event(new RiderPendingAcceptance($user, $job));        
                                                }
                                            }
                                        }
                                    }                            
                                }
                            }

                            // check if status code for merchant
                            if ($status->code == OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE) {

                                // check 1 assign job queue
                                $queue = $order->assign_job_queue;

                                // have queue & status pending
                                if ($queue && $queue->user_type == User::MERCHANT) {

                                    // check if now is past or equal to trigger time
                                    $created_at = $queue->created_at->copy()->second(0);
                                    $trigger_at = $created_at->addMinutes($queue->time_interval);
                                    if ($trigger_at->lte(now())) {

                                        // get user info
                                        $user = $queue->user;
                                        if ($user) {

                                            // get merchant info
                                            $merchant = $queue->user->merchant;
                                            if ($merchant) {

                                                // check if merchant have assigned job
                                                $assigned = AssignJob::where([
                                                    'code' => OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE,
                                                    'user_id' => $user->id, 
                                                    'order_id' => $order->id,
                                                ])->first();

                                                // not assign yet
                                                if (!$assigned) {

                                                    // get maximum bag allowed of merchant   
                                                    // pass number of washing machines & dryers                          
                                                    $max_bag = $this->calculateMaxBags($merchant->washer_quantity, $merchant->dryer_quantity);
                                                    if ($max_bag > 0) {

                                                        // get total job merchant for today
                                                        $chk_taken = AssignJob::where([
                                                            'accepted_by' => $user->id, 
                                                            'is_accepted' => true
                                                        ])
                                                        ->where('code', OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE)
                                                        ->whereDate('accepted_at', now()->format('Y-m-d'))
                                                        ->get();  

                                                        // get total bag of taken
                                                        $taken = 0;
                                                        if (count($chk_taken) > 0) {
                                                            foreach ($chk_taken as $take) {
                                                                $taken += $take->order->quantity;
                                                            }
                                                        }

                                                        // taken job not exceed allowed bag 
                                                        if ($taken <= $max_bag) {

                                                            // get balance of allowed bag
                                                            $balance = $max_bag - $taken;

                                                            // ordered bag less than or equal max allowed bag 
                                                            if ($order->quantity <= $balance) {

                                                                // set previous assignee queue false
                                                                AssignJob::where([
                                                                    'code' => OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE,
                                                                    'order_id' => $order->id,
                                                                ])->update(['is_queue' => false]);

                                                                // update queue status
                                                                $queue->status = AssignJobQueue::QUEUED;
                                                                $queue->save();

                                                                // assign job to merchant
                                                                $job = new AssignJob();
                                                                $job->code = OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE;
                                                                $job->user_id = $user->id;
                                                                $job->order_id = $status->order_id;
                                                                $job->order_status_id = $status->id;
                                                                $job->is_queue = true;
                                                                $job->save();

                                                                // send pn to merchant
                                                                event(new MerchantPendingAcceptance($user, $job));                                                      
                                                            }
                                                        }
                                                    }  
                                                }
                                            }
                                        }
                                    }
                                }

                                // doesn't have any job queue or status queued
                                else {

                                    // check if trigger 1st cronjob
                                    if (!$status->is_check_queue) {

                                        $first_check = true;                                        

                                        // get nearby merchant
                                        $users = User::role('merchant')
                                            ->has('merchant') 
                                            ->whereHas('covered_locations', function($q) use ($city_name) {
                                                $q->where('is_active', true);
                                                $q->whereHas('city', function ($c) use ($city_name) {
                                                    $c->where('name', $city_name);
                                                });
                                            })
                                            ->where('is_duty', true)
                                            ->whereNotNull('latitude')
                                            ->whereNotNull('longitude')
                                            ->active()
                                            ->get();

                                        // See the matching comment in the rider
                                        // block above — same fix.
                                        $total_merchants = count($users);

                                        // found nearby merchants
                                        if (count($users) > 0) {

                                            $user = null;
                                            $coordinate = [];
                                            foreach ($users as $user) {

                                                // check if merchant have assigned job
                                                $assigned = AssignJob::where([
                                                    'code' => OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE,                                    
                                                    'user_id' => $user->id, 
                                                    'order_id' => $order->id,
                                                ])->first();

                                                // not assigned yet
                                                if (!$assigned) {

                                                    // check coordinate
                                                    $coordinate[$user->id] = $this->calculateDistanceInMeters(
                                                        $location['latitude'], 
                                                        $location['longitude'], 
                                                        $user->latitude, 
                                                        $user->longitude
                                                    );
                                                }
                                            }

                                            // have coordinates
                                            if (count($coordinate) > 0) {

                                                // check the 4 nearest 
                                                $nearests = collect($coordinate)
                                                    ->sort()  
                                                    ->take(4);

                                                // check the nearest 
                                                $nearest = collect($coordinate)
                                                    ->sort()
                                                    ->keys()
                                                    ->first();

                                                // insert queue
                                                $queueOrder = 1;
                                                $interval = 0;
                                                foreach ($nearests as $user_id => $distance) {
                                                    $interval += 10;
                                                    AssignJobQueue::updateOrCreate(
                                                        [
                                                            'order_id' => $status->order_id,
                                                            'user_id'  => $user_id,
                                                        ],
                                                        [
                                                            'user_type' => User::MERCHANT,
                                                            'distance' => $distance,
                                                            'status'   => ($nearest == $user_id)
                                                                ? AssignJobQueue::QUEUED
                                                                : AssignJobQueue::PENDING,
                                                            'queue_position' => $queueOrder,
                                                            'time_interval' => $interval,
                                                        ]
                                                    );
                                                    $queueOrder++;
                                                    $total_merchants++;                                                    
                                                }

                                                // assign nearest rider
                                                $user = User::find($nearest);                                    
                                                if ($user) {

                                                    // get merchant info
                                                    $merchant = $user->merchant;
                                                    if ($merchant) {

                                                        // get maximum bag allowed of merchant   
                                                        // pass number of washing machines & dryers                          
                                                        $max_bag = $this->calculateMaxBags($merchant->washer_quantity, $merchant->dryer_quantity);
                                                        if ($max_bag > 0) {

                                                            // get total job merchant for today
                                                            $chk_taken = AssignJob::where([
                                                                'accepted_by' => $user->id, 
                                                                'is_accepted' => true
                                                            ])
                                                            ->whereDate('accepted_at', now()->format('Y-m-d'))
                                                            ->get();  

                                                            // get total bag of taken
                                                            $taken = 0;
                                                            if (count($chk_taken) > 0) {
                                                                foreach ($chk_taken as $take) {
                                                                    $taken += $take->order->quantity;
                                                                }
                                                            }

                                                            // taken job not exceed allowed bag 
                                                            if ($taken <= $max_bag) {

                                                                // get balance of allowed bag
                                                                $balance = $max_bag - $taken;

                                                                // ordered bag less than or equal max allowed bag 
                                                                if ($order->quantity <= $balance) {

                                                                    // set previous assignee queue false
                                                                    AssignJob::where([
                                                                        'code' => OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE,
                                                                        'order_id' => $order->id,
                                                                    ])->update(['is_queue' => false]);

                                                                    // assign job to merchant
                                                                    $job = new AssignJob();
                                                                    $job->code = OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE;
                                                                    $job->user_id = $user->id;
                                                                    $job->order_id = $status->order_id;
                                                                    $job->order_status_id = $status->id;
                                                                    $job->is_queue = true;
                                                                    $job->save();

                                                                    // send pn to merchant
                                                                    event(new MerchantPendingAcceptance($user, $job));                                                      
                                                                }
                                                            }
                                                        }                    
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                    } catch (\Throwable $th) {
                        // Previously the try/catch here wrapped the ENTIRE
                        // order (all of its statuses together) — so if
                        // rider matching threw for this order, merchant
                        // matching for the SAME order never even got a
                        // chance to run in that tick either, since the
                        // whole order was abandoned via `continue`. Rider
                        // and merchant are processed as separate iterations
                        // of this same status loop, so catching here keeps
                        // them properly isolated from each other: one
                        // role's failure can no longer silently block the
                        // other role's assignment for the same order.
                        \Log::error('AssignOrderToRiderAndMerchant failed for order status', [
                            'order_id' => $order->id ?? null,
                            'status_code' => $status->code ?? null,
                            'message' => $th->getMessage(),
                            'file' => $th->getFile(),
                            'line' => $th->getLine(),
                        ]);
                        continue;
                    }
                        
                        // save is_check_queue (indicator already check the first time)
                        //
                        // Previously set unconditionally — combined with
                        // total_riders/total_merchants never actually being
                        // populated (see the fix above), this meant an order
                        // that failed to find any candidate on its very first
                        // cron tick (e.g. no rider on duty yet) was locked out
                        // of ever being retried, even after a rider came
                        // online moments later. Now only locks in once a
                        // candidate was genuinely found for this status's
                        // role — otherwise it retries on the next tick.
                        $foundCandidateForThisStatus =
                            ($status->code == OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE && $total_riders > 0)
                            || ($status->code == OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE && $total_merchants > 0);
                        if ($foundCandidateForThisStatus) {
                            $status->is_check_queue = true;
                            $status->save();
                        }
                    }
                }

                // check total rider & merchant (1st cronjob)
                //
                // Previously this only looked at $total_riders/$total_merchants
                // as computed THIS tick — but a role whose is_check_queue was
                // already true from an earlier successful match gets its
                // re-check SKIPPED entirely on later ticks (see the
                // `if (!$status->is_check_queue)` gate above), leaving its
                // total at the loop's initial 0 regardless of whether it was
                // actually matched before. That made an order where merchant
                // was already found, but rider genuinely has zero candidates
                // this tick, get wrongly flagged as "no active rider or
                // merchant was found nearby" and pushed to admin — even
                // though half of that message was false. Checking for an
                // actual existing job row per role reflects whether anyone
                // was EVER found, not just this tick's re-check pass.
                $riderJobExists = AssignJob::where('order_id', $order->id)
                    ->where('code', OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE)
                    ->exists();
                $merchantJobExists = AssignJob::where('order_id', $order->id)
                    ->where('code', OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE)
                    ->exists();
                if ($first_check && $total_riders == 0 && $total_merchants == 0 && !$riderJobExists && !$merchantJobExists) {

                    // set to admin pending assign
                    $order->is_pending_assign = true;
                    $order->save();

                    $this->notifyAdminPendingAssign($order, 'No active rider or merchant was found nearby.');
                }

                // trigger 2nd cronjob onwards)
                if (!$order->is_pending_assign) {

                    // if all queue is completed check acceptance rider & merchant
                    if ($order->is_queue_completed) {

                        // check last queue
                        $last_updated = AssignJobQueue::where('order_id', $order->id)->orderBy('updated_at', 'desc')->first();
                        if ($last_updated) {
                            $updated_at = $last_updated->updated_at;
                            $time_interval = $last_updated->time_interval; 

                            // check current time with last time interval                            
                            if (Carbon::parse($updated_at)->second(0)->addMinutes($time_interval)->lte(now())) {

                                // check if rider accepted the job
                                $rider_accepted = $order->rider()->exists();

                                // check if merchant accepted the job
                                $merchant_accepted = $order->merchant()->exists();

                                // set to pending assign                    
                                if (!$rider_accepted || !$merchant_accepted) {
                                    $order->is_pending_assign = true;
                                    $order->save();

                                    $this->notifyAdminPendingAssign($order, 'The nearest riders/merchants queue was exhausted without anyone accepting.');

                                    // remove last queue from home
                                    AssignJob::where([
                                        'order_id' => $order->id
                                    ])
                                    ->whereIn('code', [OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE, OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE])
                                    ->update(['is_queue' => false]);
                                }
                            }
                        }
                    }

                    // if no pending queue set all queue is completed
                    if (!$order->has_pending_queue()) {                        
                        $order->is_queue_completed = true;
                        $order->save();
                    }
                }

                } catch (\Throwable $th) {
                    // Previously nothing here — an uncaught exception
                    // anywhere in this order's rider/merchant matching
                    // (a bad data value, a null relation, anything)
                    // silently killed the ENTIRE command run for every
                    // remaining order in this batch, not just this one.
                    // Worse, since whatever DB writes already happened
                    // for this order before the crash point persist
                    // (e.g. rider gets assigned, merchant doesn't), the
                    // order looks "partially fixed" every single tick,
                    // forever, with zero visible error anywhere except
                    // this log line. Isolating + logging per order means
                    // one broken order can no longer block every other
                    // pending order from being auto-assigned, and the
                    // real cause is now actually visible.
                    \Log::error('AssignOrderToRiderAndMerchant failed for order', [
                        'order_id' => $order->id ?? null,
                        'message' => $th->getMessage(),
                        'file' => $th->getFile(),
                        'line' => $th->getLine(),
                    ]);
                    continue;
                }

            }
        }
    }

    /**
     * Emails the admin (Settings > Admin Email) when an order needs
     * manual rider/merchant assignment — covers the "system needs to
     * send email notification to admin" requirement. WhatsApp
     * notification isn't included here — that needs WhatsApp Business
     * API credentials this codebase doesn't currently have configured;
     * email is the reliable channel available right now.
     *
     * @param  \App\Models\Order $order
     * @param  string $reason
     * @return void
     */
    protected function notifyAdminPendingAssign(Order $order, string $reason): void
    {
        try {
            $setting = Setting::find(1);
            $adminEmail = $setting->admin_email ?? null;
            if (!$adminEmail) {
                return;
            }

            $subject = 'Auto Maid: Order #' . $order->id . ' needs manual assignment';
            $body = sprintf(
                '<p>Order #%s (%s) could not be auto-assigned to a rider/merchant.</p><p>Reason: %s</p><p>Please assign manually from the admin panel: Orders &gt; %s.</p>',
                $order->id,
                $order->series_no ?? '',
                $reason,
                $order->id
            );

            (new OneSignalService())->sendEmail($adminEmail, $subject, $body);
        } catch (\Throwable $th) {
            // Deliberately swallow — a failed admin notification email
            // must never block the assignment cron from continuing to
            // process the rest of the queue.
        }
    }
}
