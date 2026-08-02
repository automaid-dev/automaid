<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class Bag extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'bags';

    // status order bag
    const PENDING = 'pending'; // pending payment
    const PAID = 'paid'; // after make payment
    
    // // status order booking
    // const BOOKING_PROCESSING = 'processing';
    // const BOOKING_DELIVERED = 'delivered';
    // const BOOKING_CANCELLED = 'cancelled';
    
    const SCANNED = 'scanned'; // scan bag    
    const PROCESSING = 'processing';
    const DELIVERED = 'delivered';
    const CANCELLED = 'cancelled';

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('bags');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
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
     * [scopeProcessing description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', self::PROCESSING);
    }

    /**
     * [scopeCompleted description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::COMPLETED);
    }

    /**
     * [scopeCanceled description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopeCanceled($query)
    {
        return $query->where('status', self::CANCELLED);
    }



}


