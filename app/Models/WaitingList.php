<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class WaitingList extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'waiting_lists';

    protected $fillable = [
        'name',
        'email',
        // Renamed from 'phone' by an earlier migration
        // (renameColumn('phone', 'mobile_no')) — this was left stale
        // here, which is exactly what caused
        // CoverageController::joinWaitingList's "Unknown column
        // 'phone'" error (it was writing 'phone' as a fillable key
        // into a table that no longer has that column).
        'mobile_no',
        'state',
        'city',
        'postcode',
    ];

    const ACTIVE = 'active';
    const INACTIVE = 'inactive';

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('waiting_lists');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * [city description]
     * @return [type] [description]
     */
    public function city()
    {
        return $this->belongsTo('App\Models\City');
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
     * [scopeInactive description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopeInactive($query)
    {
        return $query->where('status', Self::INACTIVE);
    }

    
}
