<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class Payment extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'payments';

    const FIUU = 'fiuu';
    const FIUU_RECURRING = 'fiuu-recurring';

    const PENDING = 'pending';
    const PAID = 'paid';

    const PURCHASE_BAG = 'purchase_bag';
    const SUBSCRIPTION = 'subscription';
    const BOOKING = 'booking';

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('payments');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
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
     * [order description]
     * @return [type] [description]
     */
    public function order()
    {
        return $this->belongsTo(\App\Models\Order::class);
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
     * [current_recurring description]
     * @return [type] [description]
     */
    public function current_recurring()
    {
        return $this->hasOne('App\Models\PaymentRecurring', 'payment_id', 'id')->where(['status', 'paid'])->latest();
    }

    /**
     * [recurrings description]
     * @return [type] [description]
     */
    public function recurrings()
    {
        return $this->hasMany('App\Models\PaymentRecurring', 'payment_id');
    }

}
