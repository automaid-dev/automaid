<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;
use Illuminate\Support\Facades\Storage;

class Ticket extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'tickets';
    protected $appends = ['image_url'];

    const OPEN = 'open';
    const RESOLVED = 'resolved';
    const CLOSED = 'closed';

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('tickets');
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
            $series_no = 'ST' . date('y') . sprintf('%010d', random_int(1, 9999999999));
        }         
        while (self::where('series_no', $series_no)->exists());
        return $series_no;
    }

    /**
     * [getImageUrlAttribute description]
     * @return [type] [description]
     */
    public function getImageUrlAttribute()
    {
        return $this->image ? Storage::disk('s3')->url($this->image) : null;
    }

    /**
     * [scopeOpen description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopeOpen($query)
    {
        return $query->where('status', self::OPEN);
    }
    
    /**
     * [scopeResolved description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopeResolved($query)
    {
        return $query->where('status', self::RESOLVED);
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
     * [replies description]
     * @return [type] [description]
     */
    public function replies()
    {
        return $this->hasMany('App\Models\TicketReply', 'ticket_id');
    }
}
