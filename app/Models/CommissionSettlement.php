<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class CommissionSettlement extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'commission_settlements';

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('commission_settlements');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            }
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * The rider or merchant this payout was made to.
     * @return [type] [description]
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Admin who performed the settlement.
     * @return [type] [description]
     */
    public function paidBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'paid_by');
    }

    /**
     * Itemized penalty/chargeback/refund/adjustment lines making up
     * total_deductions.
     * @return [type] [description]
     */
    public function deductions()
    {
        return $this->hasMany(\App\Models\CommissionSettlementDeduction::class);
    }

    /**
     * Every CommissionTransaction covered by this settlement.
     * @return [type] [description]
     */
    public function transactions()
    {
        return $this->hasMany(\App\Models\CommissionTransaction::class);
    }

    /**
     * Per-transaction audit rows created alongside this settlement.
     * @return [type] [description]
     */
    public function payments()
    {
        return $this->hasMany(\App\Models\CommissionPayment::class);
    }
}
