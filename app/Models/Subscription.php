<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class Subscription extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'subscriptions';

    const PENDING = 'pending';
    const ACTIVE = 'active';
    const CANCELLED = 'cancelled';
    const INACTIVE = 'inactive';

    // Subscription package tiers. Stored in `plan_code`. Prices and order
    // quotas for these live in Settings (subscription_bronze_price /
    // subscription_bronze_orders, etc.) — see SubscriptionPlanController
    // for the customer-facing list of these with current prices.
    const BRONZE = 'bronze';
    const SILVER = 'silver';
    const PLATINUM = 'platinum';

    /**
     * All valid plan codes, in display order.
     * @return array<string>
     */
    public static function planCodes(): array
    {
        return [self::BRONZE, self::SILVER, self::PLATINUM];
    }

    /**
     * Human-readable label for a plan code.
     * @param string $code
     * @return string
     */
    public static function planLabel(string $code): string
    {
        return match ($code) {
            self::BRONZE => 'Bronze',
            self::SILVER => 'Silver',
            self::PLATINUM => 'Platinum',
            default => ucfirst($code),
        };
    }

    /**
     * The order quota for this subscription's plan, read from Settings.
     * Returns null for platinum (and for any legacy subscription with no
     * plan_code) — null means "unlimited" everywhere this is used.
     *
     * @param \App\Models\Setting $setting
     * @return int|null
     */
    public function planOrderQuota(Setting $setting): ?int
    {
        return match ($this->plan_code) {
            self::BRONZE => $setting->subscription_bronze_orders,
            self::SILVER => $setting->subscription_silver_orders,
            self::PLATINUM => null, // unlimited by design
            default => null, // legacy subscriptions with no plan_code: unlimited (preserves old behaviour)
        };
    }

    /**
     * Whether this subscription still has free-bag benefit available for
     * a new booking this cycle. True whenever the plan has no quota
     * (platinum, or a legacy no-plan subscription), or usage this cycle
     * hasn't reached the quota yet.
     *
     * @param \App\Models\Setting $setting
     * @return bool
     */
    public function hasOrderQuotaRemaining(Setting $setting): bool
    {
        $quota = $this->planOrderQuota($setting);
        if ($quota === null) {
            return true;
        }
        return $this->orders_used_current_cycle < $quota;
    }

    /**
     * The monthly price for this subscription's plan, read from Settings.
     * Falls back to the legacy flat `subscription_price` for subscriptions
     * with no plan_code.
     *
     * @param \App\Models\Setting $setting
     * @return float
     */
    public function planPrice(Setting $setting): float
    {
        return (float) match ($this->plan_code) {
            self::BRONZE => $setting->subscription_bronze_price,
            self::SILVER => $setting->subscription_silver_price,
            self::PLATINUM => $setting->subscription_platinum_price,
            default => $setting->subscription_price,
        };
    }

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('subscriptions');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * [$casts description]
     * @var [type]
     */
    protected $casts = [
        'start_at' => 'datetime',
        'renew_at' => 'datetime',
    ];

    /**
     * [scopeSubscribe description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopeSubscribe($query)
    {
        return $query->where('status', self::ACTIVE);
    }

    /**
     * [scopeActive description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::ACTIVE);
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
     * [payment description]
     * @return [type] [description]
     */
    public function payment()
    {
        return $this->belongsTo(\App\Models\Payment::class);
    }

    /**
     * [bags description]
     * @return [type] [description]
     */
    public function bags()
    {
        return $this->hasMany('App\Models\Bag', 'order_id', 'order_id')->where('status_payment', 'paid');
    }

    /**
     * [bag description]
     * @return [type] [description]
     */
    public function bag()
    {
        return $this->hasOne('App\Models\Bag', 'subscription_id')->where(['status_payment' => 'paid']);
    }

    /**
     * [recurrings description]
     * @return [type] [description]
     */
    public function recurrings()
    {
        return $this->hasMany('App\Models\PaymentRecurring', 'subscription_id');
    }

    /**
     * [recurring_latest description]
     * @return [type] [description]
     */
    public function recurring_latest()
    {
        return $this->hasOne('App\Models\PaymentRecurring', 'subscription_id')->orderBy('id', 'desc');
    }

    /**
     * [recurring_active description]
     * @return [type] [description]
     */
    public function recurring_active()
    {
        return $this->hasOne('App\Models\PaymentRecurring', 'subscription_id')->whereIn('status', ['subscription', 'subscription_renewal'])->where('status_payment', 'paid');
    }


}



