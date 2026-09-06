<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Guava\Sqids\Facades\Sqids;

/**
 * Per-transaction settlement audit record — one row created each time
 * admin settles a pending CommissionTransaction, regardless of whether
 * it was settled individually or as part of a batch (rows created in
 * the same settlement action share the same paid_at/paid_by).
 * Previously this table existed (migration ran fine) but the model
 * class itself was never created, so CommissionTransaction::payments()
 * threw "Class not found" the moment anything tried to load it.
 */
class CommissionPayment extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'commission_payments';

    const PENDING = 'pending';
    const PAID = 'paid';

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('commission_payments');
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
     * [transaction description]
     * @return [type] [description]
     */
    public function transaction()
    {
        return $this->belongsTo(\App\Models\CommissionTransaction::class, 'commission_transaction_id');
    }

    /**
     * [paidBy description]
     * @return [type] [description]
     */
    public function paidBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'paid_by');
    }

    /**
     * The settlement batch this per-transaction payment record belongs to.
     * @return [type] [description]
     */
    public function settlement()
    {
        return $this->belongsTo(\App\Models\CommissionSettlement::class, 'commission_settlement_id');
    }
}
