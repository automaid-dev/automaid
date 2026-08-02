<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class PaymentRecurring extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'payment_recurrings';

    const SUBSCRIPTION = 'subscription';
    const SUBSCRIPTION_RENEWAL = 'subscription_renewal';
    const INACTIVE = 'inactive';
    const CANCELLED = 'cancelled';

    const PAID = 'paid';
    const COMPLETE = 'complete';

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('payment_recurrings');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * [payment description]
     * @return [type] [description]
     */
    public function payment()
    {
        return $this->belongsTo(\App\Models\Payment::class);
    }

    /**
     * [subscription description]
     * @return [type] [description]
     */
    public function subscription()
    {
        return $this->belongsTo(\App\Models\Subscription::class);
    }


}
