<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class Activity extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'activities';

    const ACTIVE = 'active';
    const INACTIVE = 'inactive';

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('activities');
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
     * [order description]
     * @return [type] [description]
     */
    public function order()
    {
        return $this->belongsTo(\App\Models\Order::class);
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
     * [scopeInactive description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopeInactive($query)
    {
        return $query->where('status', Self::INACTIVE);
    }

    /**
     * [order_complete description]
     * @return [type] [description]
     */
    public function order_complete()
    {
        return $this->hasOne('App\Models\OrderComplete', 'order_id', 'order_id');
    }
    
}
