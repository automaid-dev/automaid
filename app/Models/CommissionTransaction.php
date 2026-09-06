<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class CommissionTransaction extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'commission_transactions';

    const EARNED = 'earned';
    const TRANSFERRED = 'transferred';

    const PENDING = 'pending';
    const PAID = 'paid';
    
    protected $casts = [
        'paid_at' => 'datetime',
    ];
    
    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('commission_transactions');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * [commission description]
     * @return [type] [description]
     */
    public function commission()
    {
        return $this->belongsTo(\App\Models\Commission::class);
    }

    /**
     * [payments description]
     * @return [type] [description]
     */
    public function payments()
    {
        return $this->hasMany('App\Models\CommissionPayment', 'commission_transaction_id');
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
     * The settlement batch this transaction was paid out in — null
     * until settled.
     * @return [type] [description]
     */
    public function settlement()
    {
        return $this->belongsTo(\App\Models\CommissionSettlement::class, 'commission_settlement_id');
    }



}
