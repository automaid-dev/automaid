<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class Commission extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'commissions';

    const PENDING = 'pending'; // pending payouts
    const PAID = 'paid'; // paid
    
    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('commissions');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
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
     * [scopePaid description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::PAID);
    }

    /**
     * [transactions description]
     * @return [type] [description]
     */
    public function transactions()
    {
        return $this->hasMany(\App\Models\CommissionTransaction::class);
    }


}



