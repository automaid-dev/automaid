<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Guava\Sqids\Facades\Sqids;

class Banner extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'banners';
    protected $appends = ['image_url'];

    const TARGET_CUSTOMER = 'customer';
    const TARGET_MERCHANTRIDER = 'merchantrider';
    // Shown on the very first screen of the customer app, before
    // login — see the public (no-auth) route this requires, since the
    // person has no session yet at that point.
    const TARGET_ONBOARDING = 'onboarding';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('banners');
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
     * URL — same reasoning as OrderStepPhoto::image_url and
     * Booking::pickup_photo_url: this S3 bucket has Block Public
     * Access / ACLs disabled (AWS's current default), so a direct S3
     * URL returns AccessDenied regardless of upload visibility
     * settings. Announcement::image_full_url uses the raw-URL pattern
     * and is very likely equally broken — not repeating that here for
     * a brand new feature.
     */
    public function getImageUrlAttribute()
    {
        return $this->image_path ? route('documents.banner-image', $this->hashslug) : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTarget($query, $target)
    {
        return $query->where('target', $target);
    }
}
