<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Guava\Sqids\Facades\Sqids;

class ServiceCategory extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'service_categories';

    // Matches automaid_merchantrider_scaffold's
    // register_merchant_screen.dart _serviceCategoryOptions exactly —
    // merchant capability matching (merchants.service_categories JSON
    // contains this string) depends on these staying identical.
    const DRY_CLEANING = 'Dry Cleaning';
    const SHOE_CLEANING = 'Shoe Cleaning';
    const HELMET_CLEANING = 'Helmet Cleaning';
    const WASH_AND_DRY = 'Wash & Dry';

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('service_categories');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            }
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * [items description]
     * @return [type] [description]
     */
    public function items()
    {
        return $this->hasMany(\App\Models\ServiceItem::class)->orderBy('sort_order');
    }
}
