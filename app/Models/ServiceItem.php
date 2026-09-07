<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Guava\Sqids\Facades\Sqids;

class ServiceItem extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'service_items';

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('service_items');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            }
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * [category description]
     * @return [type] [description]
     */
    public function category()
    {
        return $this->belongsTo(\App\Models\ServiceCategory::class, 'service_category_id');
    }

    /**
     * Proxied through PublicDocumentController, same as every other
     * uploaded image in this app — S3 has Block Public Access enabled.
     * @return [type] [description]
     */
    public function getImageUrlAttribute()
    {
        return $this->image_path ? route('documents.service-item-image', $this->hashslug) : null;
    }
}
