<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Guava\Sqids\Facades\Sqids;

class Notification extends Model
{
    protected $guarded = ['id'];
    protected $table = 'notifications';

    const ACCOUNT_CREATED = 'account_created';
    const BAG_PURCHASED = 'bag_purchased';
    const SUBSCRIPTION_CREATED = 'subscription_created';
    const SUBSCRIPTION_CANCELLED = 'subscription_cancelled';
    const NEW_BOOKING = 'new_booking';

    protected static function booted()
    {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('notifications');
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
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * [order description]
     * @return [type] [description]
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
