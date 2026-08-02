<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Guava\Sqids\Facades\Sqids;
use Illuminate\Support\Facades\Storage;

class Announcement extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;    
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'announcements';
    protected $appends = ['image_full_url'];

    const DRAFT = 'draft';
    const PUBLISHED = 'published';
    
    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted() {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('announcements');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } 
            while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * [scopePublished description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopePublished($query)
    {
        return $query->where('status', self::PUBLISHED);
    }

    /**
     * [scopeDraft description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::DRAFT);
    }
    
    /**
     * [getImageFullUrlAttribute description]
     * @return [type] [description]
     */
    public function getImageFullUrlAttribute()
    {
        return $this->image_url ? Storage::disk('s3')->url($this->image_url) : null;
    }

}
