<?php

namespace App\Console\Commands;
use App\Models\OrderComplete;
use App\Models\Activity;
use App\Models\AssignJob;

use Illuminate\Console\Command;

class AutoInsertActivityNextDayDelivery extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automaid:auto-insert-activity-next-day-delivery';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $delivered = OrderComplete::latest()->notRating()->get();
        if (count($delivered) > 0) {
            foreach ($delivered as $deliver) {

                $customer_id = null;
                $rider_id = null;
                $merchant_id = null;

                // get user id
                $orders = AssignJob::where(['order_id' => $deliver->order_id])->whereIn('code', ['05', '16', '26'])->get();
                if (count($orders) == 3) {
                    foreach ($orders as $status) {
                        if ($status->code == '05') {
                            $customer_id = $status->user_id;
                        }
                        if ($status->code == '16') {
                            $rider_id = $status->user_id;
                        }
                        if ($status->code == '26') {
                            $merchant_id = $status->user_id;
                        }
                    }

                    // insert activity
                    $insert = 0;
                    $user_types = [
                        ['customer', $customer_id], 
                        // ['rider', $rider_id], 
                        // ['merchant', $merchant_id],
                    ];
                    foreach ($user_types as $type) {

                        // check activity
                        $activity = Activity::where([
                            'order_id' => $deliver->order_id, 
                            'user_id' => $type[1], 
                            'user_type' => $type[0],
                        ])->first();
                        if (!$activity) {
                            $data = new Activity();
                            $data->order_id = $deliver->order_id;
                            $data->user_id = $type[1];
                            $data->user_type = $type[0];
                            $data->title = 'Order Delivered';
                            $data->status = Activity::ACTIVE;
                            $data->save();
                            $insert++;
                        }
                    }

                    
                    if ($insert > 0) {
                        $deliver->is_auto_rated = true;
                        $deliver->save();
                    }
                }

            }
        }
    }
}
