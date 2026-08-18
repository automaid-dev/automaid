<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class Setting extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'settings';
    protected $appends = ['terms_conditions_url'];

    /**
     * Full URL for the admin-uploaded Terms & Conditions PDF — same
     * pattern as User::getAvatarUrlAttribute(). Null if none uploaded yet.
     * @return string|null
     */
    public function getTermsConditionsUrlAttribute()
    {
        // Proxied through PublicDocumentController rather than a raw S3
        // URL — the S3 bucket has Block Public Access / ACLs disabled
        // (AWS's current default), so a direct S3 URL returns
        // AccessDenied regardless of the object's own visibility
        // setting. This works without needing to touch bucket-wide
        // public-access settings at all — same bucket holds sensitive
        // rider/merchant verification documents, so that's deliberately
        // left alone.
        return $this->terms_conditions ? route('documents.terms-conditions') : null;
    }

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('settings');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }
}
