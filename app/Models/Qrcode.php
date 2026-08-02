<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class Qrcode extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'qrcodes';

    const PENDING = 'pending';
    const SCANNED = 'scanned';

    const AUTO = 'auto';
    const MANUAL = 'manual';

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('qrcodes');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * [getNextSeriesNo description]
     * @return [type] [description]
     */
    public function getNextSeriesNo()
    {
        do {
            $series_no = 'LB' . date('y') . sprintf('%010d', random_int(1, 9999999999));
        }         
        while (self::where('series_no', $series_no)->exists());
        return $series_no;
    }
    
    /**
     * [scopePending description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopePending($query)
    {
        return $query->where('status', self::PENDING);
    }

    /**
     * [scopeScanned description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopeScanned($query)
    {
        return $query->where('status', self::SCANNED);
    }

    /**
     * [user description]
     * @return [type] [description]
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }


}
