<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class Commission extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'commissions';
    // Exposes both as plain fields on every API response that already
    // eager-loads this model (both Merchant and Rider ProfileController
    // already load 'wallet.transactions', so no controller changes are
    // needed for these to show up) — lets the app display them
    // directly rather than summing `transactions` client-side itself.
    protected $appends = ['lifetime_earnings', 'pending_settlement'];

    const PENDING = 'pending'; // pending payouts
    const PAID = 'paid'; // paid
    
    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('commissions');
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
     * [scopePaid description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::PAID);
    }

    /**
     * [transactions description]
     * @return [type] [description]
     */
    public function transactions()
    {
        return $this->hasMany(\App\Models\CommissionTransaction::class);
    }

    /**
     * Alias for `balance` — balance is already computed as the sum of
     * every transaction's final_amount regardless of paid/pending
     * status (see insertCommissionEwallet), which is exactly "lifetime
     * earnings". Named explicitly here so the API response is
     * self-describing rather than the app having to know that
     * "balance" happens to mean lifetime total.
     * @return float
     */
    public function getLifetimeEarningsAttribute()
    {
        return (float) $this->balance;
    }

    /**
     * Sum of this wallet's not-yet-settled transactions — the amount
     * still owed, as distinct from lifetime_earnings above. Previously
     * nothing on this model (or exposed via the API) distinguished
     * "ever earned" from "still owed"; the app only ever had
     * `balance`, an ever-growing lifetime total that never reflected
     * whether a payout had already happened.
     * @return float
     */
    public function getPendingSettlementAttribute()
    {
        return (float) $this->transactions()
            ->where('status', CommissionTransaction::PENDING)
            ->sum('final_amount');
    }
}



