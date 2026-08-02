<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;
use Illuminate\Support\Facades\Storage;

class OrderComplete extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'order_completes';
    protected $appends = ['image_first_url', 'image_second_url', 'image_third_url'];

    const DELIVERED = 'delivered';

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('order_completes');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * [getImageFirstUrlAttribute description]
     * @return [type] [description]
     */
    public function getImageFirstUrlAttribute()
    {
        return $this->image1 ? Storage::disk('s3')->url($this->image1) : null;
    }

    /**
     * [getImageSecondUrlAttribute description]
     * @return [type] [description]
     */
    public function getImageSecondUrlAttribute()
    {
        return $this->image2 ? Storage::disk('s3')->url($this->image2) : null;
    }

    /**
     * [getImageThirdUrlAttribute description]
     * @return [type] [description]
     */
    public function getImageThirdUrlAttribute()
    {
        return $this->image3 ? Storage::disk('s3')->url($this->image3) : null;
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
     * [scopeNotRating description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopeNotRating($query)
    {
        // return $query->whereNull('rate_rider_star')->whereNull('rate_rider_comment')->whereNull('rate_merchant_star')->whereNull('rate_merchant_comment');
        return $query->where('is_rated', false);
    }
    
}
