<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class Transaction extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'transactions';

    // type
    const PURCHASE_BAG = 'purchase_bag';
    const SUBSCRIPTION = 'subscription';
    const SUBSCRIPTION_RENEWAL = 'subscription_renewal';
    const BOOKING = 'booking'; // or order

    // status
    const PENDING = 'pending';
    const PAID = 'paid';
    const REFUNDED = 'refunded';

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('transactions');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
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
     * [payment description]
     * @return [type] [description]
     */
    public function payment()
    {
        return $this->belongsTo(\App\Models\Payment::class);
    }

    /**
     * Which gateway took this payment: 'gkash' or 'fiuu'.
     *
     * payments.payment_method is set by each gateway's callback ('gkash',
     * 'fiuu', 'fiuu-recurring'). Renewals point at the original sign-up
     * payment, which is on the same gateway the subscription is locked to.
     * Rows with no method predate GKash, so they were Fiuu.
     */
    public function gatewayCode(): string
    {
        $method = $this->payment?->payment_method;
        if ($method === Payment::GKASH) {
            return 'gkash';
        }
        if ($method === Payment::FIUU || $method === Payment::FIUU_RECURRING) {
            return 'fiuu';
        }
        return $this->order?->payment_gateway === 'gkash' ? 'gkash' : 'fiuu';
    }

    public static function gatewayLabel(string $code): string
    {
        return $code === 'gkash' ? 'GKash' : 'Fiuu';
    }

    /** Filter helper: transactions taken by $code ('gkash' or 'fiuu'). */
    public function scopeGateway($query, string $code)
    {
        $isGkash = fn ($q) => $q
            ->whereHas('payment', fn ($p) => $p->where('payment_method', Payment::GKASH))
            ->orWhere(fn ($q2) => $q2
                ->whereDoesntHave('payment', fn ($p) => $p->whereNotNull('payment_method'))
                ->whereHas('order', fn ($o) => $o->where('payment_gateway', 'gkash')));

        return $code === 'gkash' ? $query->where($isGkash) : $query->whereNot($isGkash);
    }

}
