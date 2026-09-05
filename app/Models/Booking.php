<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Booking extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'bookings';
    protected $appends = ['landmark_picture_url'];

    const ACTIVE = 'active';
    const PENDING = 'pending';
    const CANCEL = 'cancel';
    
    const OUTLET = 'outlet'; // Pickup
    const WASH = 'wash'; // Wash Complete
    const CUSTOMER = 'customer'; // Order Delivered

    /**
     * [$casts description]
     * @var [type]
     */
    protected $casts = [
        'pickup_date' => 'datetime',
    ];

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('bookings');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * [getPickupDateAttribute description]
     * @param  [type] $value [description]
     * @return [type]        [description]
     */
    public function getPickupDateAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('Y-m-d') : null;
    }

    /**
     * [getLandmarkPictureUrlAttribute description]
     * @return [type] [description]
     */
    /**
     * Proxied through PublicDocumentController rather than a raw S3
     * URL — same reasoning as pickup_photo_url below: this bucket has
     * Block Public Access enabled, so a direct S3 URL returns
     * AccessDenied. This was flagged as broken when pickup_photo_url
     * was added but left unfixed at the time since it wasn't in scope
     * then — fixing it now while directly touching this same pickup
     * info display.
     */
    public function getLandmarkPictureUrlAttribute()
    {
        return $this->landmark_picture ? route('documents.landmark-picture', $this->hashslug) : null;
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
     * [order description]
     * @return [type] [description]
     */
    public function order()
    {
        return $this->belongsTo(\App\Models\Order::class);
    }

    /**
     * [pickup_location description]
     * @return [type] [description]
     */
    public function pickup_location()
    {
        return $this->hasOne('App\Models\Address', 'id', 'pickup_location_id');
    }

    /**
     * [customer_order_status description]
     * @return [type] [description]
     */
    public function customer_order_status()
    {
        return $this->hasMany('App\Models\OrderStatus', 'order_id', 'order_id')
            ->whereIn('code', ['01', '02', '03', '04', '05']);
    }

    /**
     * [customer_and_rider_status description]
     * @return [type] [description]
     */
    public function customer_and_rider_status()
    {
        return $this->hasMany('App\Models\OrderStatus', 'order_id', 'order_id')
            ->where(['is_done' => true])
            ->whereNotNull('done_at')
            ->whereIn('code', ['01', '02', '03', '04', '05', '11', '12', '13', '14', '15', '16']);
    }

    /**
     * [customer_and_rider_status_exclude_delivered description]
     * @return [type] [description]
     */
    public function customer_and_rider_status_exclude_delivered()
    {
        return $this->hasMany('App\Models\OrderStatus', 'order_id', 'order_id')
            ->whereIn('code', ['01', '02', '03', '04', '05', '11', '12', '13', '14', '15', '16']);
    }

    /**
     * [rider_order_status description]
     * @return [type] [description]
     */
    public function rider_order_status()
    {
        return $this->hasMany('App\Models\OrderStatus', 'order_id', 'order_id')
            ->whereIn('code', ['11', '12', '13', '14', '15', '16']);
    }

    /**
     * [merchant_order_status description]
     * @return [type] [description]
     */
    public function merchant_order_status()
    {
        return $this->hasMany('App\Models\OrderStatus', 'order_id', 'order_id')
            ->whereIn('code', ['21', '22', '23', '24', '25', '26']);
    }

    /**
     * [all_order_status description]
     * @return [type] [description]
     */
    public function all_order_status()
    {
        return $this->hasMany('App\Models\OrderStatus', 'order_id', 'order_id')->orderBy('code', 'asc');
    }

    /**
     * [delivered description]
     * @return [type] [description]
     */
    public function delivered()
    {
        return $this->hasOne('App\Models\OrderComplete', 'order_id', 'order_id')
            ->where(function ($q) {
                $q->where('is_rated', true)
                  ->orWhere('is_auto_rated', true);
            });
    }

    /**
     * [delivered_not_rated description]
     * @return [type] [description]
     */
    public function delivered_not_rated()
    {
        return $this->hasOne('App\Models\OrderComplete', 'order_id', 'order_id')->where('is_rated', 0);
    }

}
