<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class AddOn extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'add_ons';

    const ACTIVE = 'active';
    const INACTIVE = 'inactive';

    const APPLICABLE_NORMAL = 'normal';
    const APPLICABLE_DRY_CLEANING = 'dry_cleaning';
    const APPLICABLE_BOTH = 'both';
    
    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('add_ons');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
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
     * Filters to add-ons applicable to a given booking type — an
     * add-on set to 'both' always matches, regardless of $type.
     * @param  [type] $query
     * @param  string $type  self::APPLICABLE_NORMAL or self::APPLICABLE_DRY_CLEANING
     * @return [type]
     */
    public function scopeApplicableTo($query, string $type)
    {
        return $query->where(function ($q) use ($type) {
            $q->where('applicable_to', $type)->orWhere('applicable_to', self::APPLICABLE_BOTH);
        });
    }
}
