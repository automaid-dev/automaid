<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;
use Illuminate\Support\Facades\Storage;

class Rider extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'riders';
    protected $appends = ['ic_front_url', 'ic_back_url', 'license_front_url', 'license_back_url', 'jpj_grant_url'];

    const ACTIVE = 'active';
    const INACTIVE = 'inactive';
    const PENDING = 'pending';
    
    const TYPE_GIG_WORKER = 1;
    const TYPE_STAFF_AUTOMAID = 2;

    const MOTORCYCLE = 'motorcycle';

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('riders');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * [getIcFrontUrlAttribute description]
     * @return [type] [description]
     */
    public function getIcFrontUrlAttribute()
    {
        return $this->ic_front ? Storage::disk('s3')->url($this->ic_front) : null;
    }

    /**
     * [getIcBackUrlAttribute description]
     * @return [type] [description]
     */
    public function getIcBackUrlAttribute()
    {
        return $this->ic_back ? Storage::disk('s3')->url($this->ic_back) : null;
    }

    /**
     * [getLicenseFrontUrlAttribute description]
     * @return [type] [description]
     */
    public function getLicenseFrontUrlAttribute()
    {
        return $this->license_front ? Storage::disk('s3')->url($this->license_front) : null;
    }

    /**
     * [getLicenseBackUrlAttribute description]
     * @return [type] [description]
     */
    public function getLicenseBackUrlAttribute()
    {
        return $this->license_back ? Storage::disk('s3')->url($this->license_back) : null;
    }

    /**
     * [getJpjGrantUrlAttribute description]
     * @return [type] [description]
     */
    public function getJpjGrantUrlAttribute()
    {
        return $this->jpj_grant ? Storage::disk('s3')->url($this->jpj_grant) : null;
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
     * [user description]
     * @return [type] [description]
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * [bank description]
     * @return [type] [description]
     */
    public function bank()
    {
        return $this->hasOne('App\Models\Bank', 'name', 'bank_name');
    }
    
}
