<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class Voucher extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'vouchers';

    const ACTIVE = 'active';
    const INACTIVE = 'inactive';

    // Minimum purchase requirement — exactly one applies at a time,
    // selected via minimum_requirement_type. 'none' means no minimum.
    const MIN_REQUIREMENT_NONE = 'none';
    const MIN_REQUIREMENT_AMOUNT = 'amount';
    const MIN_REQUIREMENT_ITEMS = 'items';

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('vouchers');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * Status alone, unchanged — date-range validity (start_at/expired_at)
     * is checked separately in checkEligibility() below, not folded into
     * this scope, since callers that need to show/manage a voucher
     * (e.g. the admin edit screen) still need to find it by status even
     * outside its active date window.
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::ACTIVE);
    }

    /**
     * Checks every voucher rule at once — date window, minimum purchase
     * requirement, total/per-customer usage limits, and the cumulative
     * discount amount cap — against a specific customer and order.
     * Centralizing this here (rather than scattering checks across every
     * caller) means BookingController and any future caller apply
     * identical rules.
     *
     * @param  int   $userId
     * @param  float $orderSubtotal   pre-discount amount the minimum purchase requirement checks against
     * @param  int   $itemCount       bag/piece count the minimum items requirement checks against
     * @return array{eligible: bool, reason: ?string}
     */
    public function checkEligibility(int $userId, float $orderSubtotal, int $itemCount): array
    {
        $now = now();
        if ($this->start_at && $now->lt($this->start_at)) {
            return ['eligible' => false, 'reason' => 'This voucher is not active yet.'];
        }
        if ($this->expired_at && $now->gt($this->expired_at)) {
            return ['eligible' => false, 'reason' => 'This voucher has expired.'];
        }

        if ($this->minimum_requirement_type === self::MIN_REQUIREMENT_AMOUNT && $this->minimum_purchase_amount) {
            if ($orderSubtotal < (float) $this->minimum_purchase_amount) {
                return ['eligible' => false, 'reason' => 'Minimum purchase amount not met for this voucher.'];
            }
        } elseif ($this->minimum_requirement_type === self::MIN_REQUIREMENT_ITEMS && $this->minimum_total_items) {
            if ($itemCount < (int) $this->minimum_total_items) {
                return ['eligible' => false, 'reason' => 'Minimum item count not met for this voucher.'];
            }
        }

        if ($this->usage_limit !== null) {
            $totalUsed = $this->voucher_users()->count();
            if ($totalUsed >= $this->usage_limit) {
                return ['eligible' => false, 'reason' => 'This voucher has reached its total usage limit.'];
            }
        }

        if ($this->usage_limit_per_customer !== null) {
            $usedByCustomer = $this->voucher_users()->where('user_id', $userId)->count();
            if ($usedByCustomer >= $this->usage_limit_per_customer) {
                return ['eligible' => false, 'reason' => 'You have already used this voucher the maximum number of times.'];
            }
        }

        if ($this->max_discount_amount_cap !== null) {
            $totalDiscountGiven = (float) $this->voucher_users()->sum('discount_amount');
            if ($totalDiscountGiven >= (float) $this->max_discount_amount_cap) {
                return ['eligible' => false, 'reason' => 'This voucher has reached its total discount cap.'];
            }
        }

        return ['eligible' => true, 'reason' => null];
    }

    /**
     * Computes the actual discount this voucher would give for an
     * order — RM flat amount, or a percentage of the subtotal. This is
     * the single place callers get an actual discount figure from a
     * Voucher, rather than reading discount_amount/discount_type
     * directly and re-deriving this logic per call site.
     *
     * @param  float $orderSubtotal
     * @return float
     */
    public function computeDiscount(float $orderSubtotal): float
    {
        $amount = (float) ($this->discount_amount ?? 0);
        // discount_type: '1' = RM flat amount, '2' = percentage
        if ((string) $this->discount_type === '2') {
            return round($orderSubtotal * ($amount / 100), 2);
        }
        return $amount;
    }

    /**
     * [voucher_users description]
     * @return [type] [description]
     */
    public function voucher_users()
    {
        return $this->hasMany('App\Models\VoucherUser', 'voucher_id', 'id');
    }

}
