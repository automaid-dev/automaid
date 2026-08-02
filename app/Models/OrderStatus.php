<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class OrderStatus extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'order_statuses';

    const CUSTOMER_WAITING_RIDER_FOR_PICKUP = '01'; // Booking (need rider info)
    const CUSTOMER_DELIVERY_TO_WASH_OUTLET = '02'; // Pickup
    const CUSTOMER_WASH_IN_PROGRESS = '03'; // Bag Receive
    const CUSTOMER_DELIVERY_TO_CUSTOMER = '04'; // Pickup Outlet (need rider info)
    const CUSTOMER_ORDER_DELIVERED = '05'; // Delivery (need rider info)

    const RIDER_PENDING_FOR_ACCEPTANCE = '11';  // Booking
    const RIDER_READY_FOR_PICKUP = '12'; // Accept Order
    const RIDER_DELIVERY_TO_WASH_OUTLET = '13'; // Pickup
    const RIDER_PICKUP_FROM_WASH_OUTLET = '14'; // Wash Complete
    const RIDER_DELIVERY_TO_CUSTOMER = '15'; // Pickup Outlet
    const RIDER_ORDER_DELIVERED = '16'; // Delivery
    const RIDER_AWAITING_WASH_TO_COMPLETE = '17'; // Awaiting wash to complete 

    const MERCHANT_PENDING_FOR_ACCEPTANCE = '21'; // Booking
    const MERCHANT_AWAITING_BAG_DELIVERY = '22'; // Accept Order
    const MERCHANT_WASH_IN_PROGRESS = '23'; // Bag Receive
    // const MERCHANT_WASH_COMPLETED = '24'; // Wash Complete
    const MERCHANT_AWAITING_RIDER_T0_PICKUP = '24'; // Awaiting rider to pickup
    // const MERCHANT_ORDER_PICKUP = '25'; // Pickup Outlet
    const MERCHANT_RIDER_EN_ROUTE_TO_CUSTOMER = '25'; // Rider en route to customer
    const MERCHANT_ORDER_DELIVERED = '26'; // Delivery

    /**
     * [$casts description]
     * @var [type]
     */
    protected $casts = [
        'done_at' => 'datetime',
    ];

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('order_statuses');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * [country description]
     * @return [type] [description]
     */
    public function order()
    {
        return $this->belongsTo(\App\Models\Order::class);
    }

    /**
     * [status description]
     * @return [type] [description]
     */
    public function status()
    {
        return $this->hasOne('App\Models\Status', 'code', 'code');
    }

    /**
     * [booking description]
     * @return [type] [description]
     */
    public function booking()
    {
        return $this->hasOne('App\Models\Booking', 'order_id', 'order_id');
    }

    /**
     * [assignee description]
     * @return [type] [description]
     */
    public function rider()
    {
        $codes = ['11', '12', '13', '14', '15', '16'];
        return $this->hasOne('App\Models\AssignJob', 'order_status_id', 'id')->whereIn('code', $codes);
    }

}
