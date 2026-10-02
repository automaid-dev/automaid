<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One monthly charge attempt on a stored card token — see the
 * create_subscription_renewal_charges_table migration.
 */
class SubscriptionRenewalCharge extends Model
{
    protected $guarded = ['id'];
    protected $table = 'subscription_renewal_charges';

    const PENDING = 'pending';
    const ACCEPTED = 'accepted'; // Fiuu took the request; waiting for the callback
    const PAID = 'paid';
    const FAILED = 'failed';

    /** Give up on a billing cycle after this many failed attempts. */
    const MAX_ATTEMPTS = 3;

    /** Prefix of the OrderID we send to Fiuu — how callbacks are recognised. */
    const REFERENCE_PREFIX = 'SR';

    protected $casts = [
        'cycle_date' => 'date',
        'result_at' => 'datetime',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function paymentRecurring()
    {
        return $this->belongsTo(PaymentRecurring::class);
    }

    public static function isReference(?string $orderId): bool
    {
        return is_string($orderId) && str_starts_with($orderId, self::REFERENCE_PREFIX)
            && self::where('reference', $orderId)->exists();
    }
}
