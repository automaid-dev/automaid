<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;

class AssignJob extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'assign_jobs';

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('assign_jobs');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * [status description]
     * @return [type] [description]
     */
    public function status()
    {
        return $this->hasOne('App\Models\Status', 'code', 'code');   
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
     * [accepted_user description]
     * @return [type] [description]
     */
    public function accepted_user()
    {
        return $this->hasOne('App\Models\User', 'id', 'accepted_by');   
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
     * [booking description]
     * @return [type] [description]
     */
    public function booking()
    {
        return $this->hasOne('App\Models\Booking', 'order_id', 'order_id');
    }

    /**
     * [order_status description]
     * @return [type] [description]
     */
    public function order_status()
    {
        return $this->hasOne('App\Models\OrderStatus', 'id', 'order_status_id');
    }

    /**
     * [queues description]
     * @return [type] [description]
     */
    public function queues()
    {
        return $this->hasMany('App\Models\AssignJobQueue', 'assign_job_id');
    }


    
}
