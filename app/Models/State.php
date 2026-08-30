<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class State extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'states';

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('states');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * [country description]
     * @return [type] [description]
     */
    public function country()
    {
        return $this->hasOne('App\Models\Country', 'code', 'country_code');
    }

    /**
     * [cities description]
     * @return [type] [description]
     */
    public function cities()
    {
        return $this->hasMany('App\Models\City', 'state_id', 'id');
    }

    public function scopeServiceCovered($query)
    {
        return $query->where('is_service_covered', true);
    }
}
