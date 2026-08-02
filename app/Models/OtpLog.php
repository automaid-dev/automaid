<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Str;

class OtpLog extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    

    protected $guarded = ['id'];
    protected $table = 'otp_logs';

    protected $fillable = [
        'data',
    ];

    /**
     * [boot description]
     * @return [type] [description]
     */
    public static function boot()
    {
        parent::boot();        
        static::creating(function ($model) {
            $model->uuid = Str::uuid();
        });
    }

}
