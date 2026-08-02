<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class Outlet extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'outlets';

    const PENDING = 'pending';
    const ACTIVE = 'active';
    const INACTIVE = 'inactive';
    
    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('outlets');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
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

}
