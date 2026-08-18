<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Guava\Sqids\Facades\Sqids;

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
     * Proxied through PublicDocumentController rather than a raw S3
     * URL — same reasoning as Setting::terms_conditions_url: this S3
     * bucket has Block Public Access / ACLs disabled (AWS's current
     * default), so a direct S3 URL returns AccessDenied regardless of
     * upload visibility settings. Every step photo uploaded via this
     * model has been equally affected — not just one file.
     * @return [type] [description]
     */
    public function getImageUrlAttribute()
    {
        return $this->image_path ? route('documents.step-photo', $this->hashslug) : null;
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
