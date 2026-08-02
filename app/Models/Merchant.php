<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;
use Illuminate\Support\Facades\Storage;

class Merchant extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'merchants';
    protected $appends = ['ic_front_url', 'ic_back_url', 'ssm_cert_url'];

    const ACTIVE = 'active';
    const INACTIVE = 'inactive';
    const PENDING = 'pending';
    
    const TYPE_OUTLET_PARTNER = 3;
    const TYPE_AUTOMAID_OUTLET = 4;

    protected $casts = [
        'service_categories' => 'array',
    ];
    
    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('merchants');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

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
     * [getSsmCertUrlAttribute description]
     * @return [type] [description]
     */
    public function getSsmCertUrlAttribute()
    {
        return $this->ssm_cert ? Storage::disk('s3')->url($this->ssm_cert) : null;
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
     * [order description]
     * @return [type] [description]
     */
    public function order()
    {
        return $this->belongsTo(\App\Models\Order::class);
    }

    /**
     * [state description]
     * @return [type] [description]
     */
    public function state()
    {
        return $this->belongsTo(\App\Models\State::class);
    }

    /**
     * [country description]
     * @return [type] [description]
     */
    public function country()
    {
        return $this->belongsTo(\App\Models\Country::class);
    }

    /**
     * [outlet description]
     * @return [type] [description]
     */
    public function outlet()
    {
        return $this->belongsTo(\App\Models\Outlet::class);
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
