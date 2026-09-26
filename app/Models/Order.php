<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class Order extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'orders';

    const PENDING = 'pending';
    const PAID = 'paid';
    const CANCELLED = 'cancelled';

    const SUBSCRIPTION = 'subscription';
    const SUBSCRIPTION_RENEWAL = 'subscription_renewal';
    const SUBSCRIPTION_UPDATE = 'subscription_update';
    const SUBSCRIPTION_UPGRADE = 'subscription_upgrade';
    const PURCHASE_BAG = 'purchase_bag';
    const BOOKING = 'booking';

    const MANUAL_BOOKING_CREATED = '01';
    const MANUAL_RIDER_MERCHANT_ACCEPT_JOB = '02';
    const MANUAL_RIDER_PICKUP_BAG_CUSTOMER = '03';
    const MANUAL_WASH_IN_PROGRESS_BAG_RECEIVED = '04';
    const MANUAL_MERCHANT_WASH_COMPLETED = '05';
    const MANUAL_RIDER_ORDER_PICKUP_OUT_DELIVERY = '06';
    const MANUAL_DELIVERY_COMPLETED_ORDER_DELIVERED = '07';

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('orders');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * [getNextSeriesNo description]
     * @param  [type] $code [description]
     * @return [type]       [description]
     */
    public function getNextSeriesNo($code)
    {
        $state_code = sprintf('%02d', intval($code));
        do {
            $series_no = 'AO' . date('y') . $state_code . sprintf('%07d', random_int(1, 9999999));
        }         
        while (self::where('series_no', $series_no)->exists());
        return $series_no;
    }

    /**
     * [$statusNames description]
     * @var [type]
     */
    public static $statusNames = [
        self::MANUAL_BOOKING_CREATED                  => 'Booking Created',
        self::MANUAL_RIDER_MERCHANT_ACCEPT_JOB        => 'Rider & Merchant Accept Job',
        self::MANUAL_RIDER_PICKUP_BAG_CUSTOMER        => 'Rider Picked Bag from Customer',
        self::MANUAL_WASH_IN_PROGRESS_BAG_RECEIVED    => 'Wash In Progress & Bag Received',
        self::MANUAL_MERCHANT_WASH_COMPLETED          => 'Merchant Wash Completed',
        self::MANUAL_RIDER_ORDER_PICKUP_OUT_DELIVERY  => 'Rider Order Picked Up & Out for Delivery',
        self::MANUAL_DELIVERY_COMPLETED_ORDER_DELIVERED => 'Delivery Completed & Order Delivered',
    ];

    /**
     * [getStatusName description]
     * @param  string $code [description]
     * @return [type]       [description]
     */
    public static function getStatusName(string $code): ?string
    {
        return self::$statusNames[$code] ?? null;
    }

    /**
     * [getStatusNameAttribute description]
     * @return [type] [description]
     */
    public function getStatusNameAttribute(): ?string
    {
        return self::getStatusName($this->status);
    }

    /**
     * [statusOptions description]
     * @return [type] [description]
     */
    public static function statusOptions(): array
    {
        return self::$statusNames;
    }

    /**
     * [user description]
     * @return [type] [description]
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * [payment description]
     * @return [type] [description]
     */
    public function payment()
    {
        return $this->hasOne('App\Models\Payment', 'order_id');
    }

    /**
     * [subscription description]
     * @return [type] [description]
     */
    public function subscription()
    {
        return $this->hasOne('App\Models\Subscription', 'order_id');
    }

    /**
     * [subscription_update description]
     * @return [type] [description]
     */
    public function subscription_update()
    {
        return $this->hasOne('App\Models\Subscription', 'user_id', 'user_id');
    }

    /**
     * [order_booking description]
     * @return [type] [description]
     */
    public function order_booking()
    {
        return $this->hasOne('App\Models\OrderBooking', 'order_id');
    }
    
    /**
     * [scopePaid description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::PAID);
    }

    /**
     * [scopePending description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopePending($query)
    {
        return $query->where('status', self::PENDING);
    }

    /**
     * [booking description]
     * @return [type] [description]
     */
    public function booking()
    {
        return $this->belongsTo(\App\Models\Booking::class);
    }

    /**
     * Photo + remark captured at each handoff step (rider pickup,
     * merchant wash start/complete, rider pickup from outlet) — see
     * OrderStepPhoto. Ordered oldest-first to match the status timeline.
     * @return [type] [description]
     */
    public function step_photos()
    {
        return $this->hasMany(\App\Models\OrderStepPhoto::class, 'order_id')->orderBy('created_at');
    }
    
    /**
     * [bag description]
     * @return [type] [description]
     */
    public function bag()
    {
        return $this->hasOne('App\Models\Bag', 'order_id')->whereIn('status_payment', ['pending']);
    }

    /**
     * [all_bags description]
     * @return [type] [description]
     */
    public function all_bags()
    {
        return $this->hasMany('App\Models\Bag', 'order_id');
    }

    /**
     * [purchased_bags description]
     * @return [type] [description]
     */
    public function bags()
    {
        return $this->hasMany('App\Models\Bag', 'order_id')->whereIn('status_payment', ['pending']);
    }

    /**
     * [bag_subscription description]
     * @return [type] [description]
     */
    public function bag_subscription()
    {
        return $this->hasOne('App\Models\Bag', 'order_id')->whereNotNull('subscription_id');
    }

    /**
     * [bag_purchases description]
     * @return [type] [description]
     */
    public function bag_purchases()
    {
        return $this->hasMany('App\Models\Bag', 'order_id')->where('status_payment', 'paid');
    }

    // /**
    //  * [bags description]
    //  * @return [type] [description]
    //  */
    // public function bags()
    // {
    //     return $this->hasMany('App\Models\Bag', 'order_booking_id');
    // }

    /**
     * [order_statuses description]
     * @return [type] [description]
     */
    public function order_statuses()
    {
        return $this->hasMany('App\Models\OrderStatus', 'order_id');
    }

    /**
     * [customer_order_statuses description]
     * @return [type] [description]
     */
    public function customer_order_statuses()
    {
        $codes = ['01', '02', '03', '04', '05']; 
        return $this->hasMany('App\Models\OrderStatus', 'order_id')->whereIn('code', $codes);
    }

    /**
     * [rider_order_statuses description]
     * @return [type] [description]
     */
    public function rider_order_statuses()
    {
        $codes = ['11', '12', '13', '14', '15', '16', '17']; 
        return $this->hasMany('App\Models\OrderStatus', 'order_id')->whereIn('code', $codes);
    }

    /**
     * [commission_transactions description]
     * @return [type] [description]
     */
    public function commission_transactions()
    {
        return $this->hasMany('App\Models\CommissionTransaction', 'order_id');
    }

    /**
     * [merchant_order_statuses description]
     * @return [type] [description]
     */
    public function merchant_order_statuses()
    {
        $codes = ['21', '22', '23', '24', '25', '26']; 
        return $this->hasMany('App\Models\OrderStatus', 'order_id')->whereIn('code', $codes);
    }

    /**
     * [order_pickups description]
     * @return [type] [description]
     */
    public function order_pickups()
    {
        return $this->hasMany('App\Models\OrderPickup', 'order_id');
    }

    /**
     * [bag_receives when merchant want to check]
     * @return [type] [description]
     */
    public function bag_receives()
    {
        return $this->hasMany('App\Models\BagReceive', 'order_id');
    }

    /**
     * [wash_completes description]
     * @return [type] [description]
     */
    public function wash_completes()
    {
        return $this->hasMany('App\Models\WashComplete', 'order_id');
    }

    /**
     * [pickup_outlets description]
     * @return [type] [description]
     */
    public function pickup_outlets()
    {
        return $this->hasMany('App\Models\PickupOutlet', 'order_id');
    }
    
    /**
     * [delivered description]
     * @return [type] [description]
     */
    public function delivered()
    {
        return $this->hasOne('App\Models\OrderComplete', 'order_id', 'id');
    }

    /**
     * [merchant description]
     * @return [type] [description]
     */
    public function merchant()
    {
        return $this->hasOne('App\Models\AssignJob', 'order_id', 'id')->where(['code' => '21', 'is_accepted' => true]);
    }

    /**
     * [merchant_pending description]
     * @return [type] [description]
     */
    public function merchant_pending()
    {
        return $this->hasOne('App\Models\AssignJob', 'order_id', 'id')->where(['code' => '21']);
    }

    /**
     * [rider description]
     * @return [type] [description]
     */
    public function rider()
    {
        return $this->hasOne('App\Models\AssignJob', 'order_id', 'id')->where(['code' => '11', 'is_accepted' => true]);
    }

    /**
     * [rider_pending description]
     * @return [type] [description]
     */
    public function rider_pending()
    {
        return $this->hasOne('App\Models\AssignJob', 'order_id', 'id')->where(['code' => '11']);
    }

    /**
     * [customer_latest_status description]
     * @return [type] [description]
     */
    public function customer_latest_status()
    {
        $codes = ['01', '02', '03', '04', '05'];         
        return $this->hasOne('App\Models\OrderStatus', 'order_id', 'id')->whereIn('code', $codes)->orderBy('code', 'desc');
    }

    /**
     * [merchant_latest_status description]
     * @return [type] [description]
     */
    public function merchant_latest_status()
    {
        $codes = ['21', '22', '23', '24', '25', '26'];         
        return $this->hasOne('App\Models\OrderStatus', 'order_id', 'id')->whereIn('code', $codes)->orderBy('code', 'desc');
    }

    /**
     * [rider_latest_status description]
     * @return [type] [description]
     */
    public function rider_latest_status()
    {
        $codes = ['11', '12', '13', '14', '15', '16', '17'];         
        return $this->hasOne('App\Models\OrderStatus', 'order_id', 'id')->whereIn('code', $codes)->orderBy('code', 'desc');        
    }

    /**
     * [transaction description]
     * @return [type] [description]
     */
    public function transaction()
    {
        return $this->hasOne('App\Models\Transaction', 'order_id');
    }

    /**
     * [order_addons description]
     * @return [type] [description]
     */
    public function order_addons()
    {
        return $this->hasMany('App\Models\OrderAddon', 'order_id');
    }

    /**
     * [assign_jobs description]
     * @return [type] [description]
     */
    public function assign_jobs()
    {
        return $this->hasMany('App\Models\AssignJob', 'order_id', 'id');
    }

    /**
     * [activities description]
     * @return [type] [description]
     */
    public function activities()
    {
        return $this->hasMany('App\Models\Activity', 'order_id', 'id');    
    }

    /**
     * [qrcode_users description]
     * @return [type] [description]
     */
    public function qrcode_users()
    {
        return $this->hasMany('App\Models\QrcodeUser', 'order_id');    
    }

    /**
     * [queues description]
     * @return [type] [description]
     */
    public function queues()
    {
        return $this->hasMany('App\Models\AssignJobQueue', 'order_id')->orderBy('queue_position', 'asc');
    }

    /**
     * [assign_job_queues description]
     * @return [type] [description]
     */
    public function assign_job_queue()
    {
        return $this->hasOne('App\Models\AssignJobQueue', 'order_id')->where('status', 'pending')->orderBy('queue_position', 'asc');
    }

    /**
     * [has_pending_queue description]
     * @return boolean [description]
     */
    public function has_pending_queue(): bool
    {
        return $this->queues()->where('status', 'pending')->exists();
    }

    /**
     * [birthday description]
     * @return [type] [description]
     */
    public function birthday()
    {
        return $this->hasOne('App\Models\BirthdayUser', 'order_id', 'id');
    }

    /**
     * [insurance description]
     * @return [type] [description]
     */
    public function insurance()
    {
        return $this->hasOne('App\Models\InsuranceUser', 'order_id', 'id');
    }

    /**
     * Human-readable wash type for a booking: "Wash & Fold" or the
     * service category name ("Dry Cleaning"). Same convention used
     * across BookingController / admin OrderResource — a booking with
     * a service_category_id (or dry-clean items) is dry cleaning,
     * without one it's the original Wash & Fold flow. Null for
     * non-booking orders (bag purchase, subscription).
     */
    public function serviceTypeLabel(): ?string
    {
        if ($this->order_type !== self::BOOKING) {
            return null;
        }

        $booking = $this->booking;
        if (!$booking) {
            return 'Wash & Fold';
        }

        if ($booking->service_category_id || !empty($booking->items)) {
            // Queried directly rather than via the relation property so
            // it doesn't get attached to (and serialized with) the booking.
            $name = $booking->service_category_id
                ? $booking->service_category()->value('name')
                : null;
            return $name ?: \App\Models\ServiceCategory::DRY_CLEANING;
        }

        return 'Wash & Fold';
    }

    /**
     * Customer pickup address as one line — same field order as the
     * admin EditOrder "Location" placeholder.
     */
    public function customerAddressText(): ?string
    {
        $location = $this->booking?->pickup_location;
        if (!$location) {
            return null;
        }

        $text = implode(', ', array_filter([
            $location->unit_no,
            $location->floor,
            $location->block,
            $location->address_line_1,
            $location->address_line_2,
            $location->address_line_3,
            trim(($location->postcode ?? '') . ' ' . ($location->city ?? '')),
            $location->state?->name,
        ]));

        return $text !== '' ? $text : null;
    }

    /**
     * The accepted merchant's name + address. Prefers the merchant's
     * outlet address, falling back to the address on the merchant
     * profile itself. Uses the accepted merchant, else the pending one;
     * null if no merchant has been assigned yet.
     *
     * @return array{name: ?string, address: ?string}|null
     */
    public function merchantAddressInfo(): ?array
    {
        // Fresh query instead of $this->merchant so the merchant's
        // user/profile records are never attached to this Order and
        // leaked into the customer endpoint's JSON.
        // Falls back to the pending (assigned, not yet accepted) merchant
        // job so the address still shows before the merchant taps Accept.
        $with = ['user.merchant.outlet.state', 'user.merchant.state'];
        $job = $this->merchant()->with($with)->first()
            ?? $this->merchant_pending()->with($with)->latest('id')->first();
        $merchantUser = $job?->user;
        $profile = $merchantUser?->merchant;
        if (!$merchantUser) {
            return null;
        }

        $format = function ($source) {
            if (!$source) {
                return null;
            }
            $text = implode(', ', array_filter([
                $source->unit_no ?? null,
                $source->floor ?? null,
                $source->block ?? null,
                $source->address_line_1 ?? null,
                $source->address_line_2 ?? null,
                $source->address_line_3 ?? null,
                trim(($source->postcode ?? '') . ' ' . ($source->city ?? '')),
                $source->state?->name,
            ]));
            return $text !== '' ? $text : null;
        };

        $outlet = $profile?->outlet;
        $address = $format($outlet) ?? $format($profile);

        return [
            'name' => $outlet?->name ?: ($profile?->company_name ?: $merchantUser->name),
            'address' => $address,
        ];
    }

    /**
     * Attach the display-only fields the apps show on order detail and
     * receipt screens (service_type, customer_address, merchant_name,
     * merchant_address). Only sets plain strings — never exposes the
     * merchant's user/profile records themselves (bank details, IC
     * documents), which matters for the customer-facing endpoint.
     */
    public function withDisplayDetails(): self
    {
        $merchant = $this->merchantAddressInfo();

        $this->setAttribute('service_type', $this->serviceTypeLabel());
        $this->setAttribute('customer_address', $this->customerAddressText());
        $this->setAttribute('merchant_name', $merchant['name'] ?? null);
        $this->setAttribute('merchant_address', $merchant['address'] ?? null);

        return $this;
    }
}
