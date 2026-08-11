<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Guava\Sqids\Facades\Sqids;
use Illuminate\Support\Facades\Storage;

class OrderStepPhoto extends Model
{
    protected $guarded = ['id'];
    protected $table = 'order_step_photos';
    protected $appends = ['image_url'];

    protected static function booted()
    {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('order_step_photos');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            }
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * [getImageUrlAttribute description]
     * @return [type] [description]
     */
    public function getImageUrlAttribute()
    {
        return $this->image_path ? Storage::disk('s3')->url($this->image_path) : null;
    }

    /**
     * [order description]
     * @return [type] [description]
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
