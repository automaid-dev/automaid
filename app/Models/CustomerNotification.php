<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Guava\Sqids\Facades\Sqids;

/**
 * In-app notification history for customers (new subscription, bag
 * purchase, booking, etc.). Deliberately NOT named `Notification` and
 * NOT using the table name `notifications` — that name is reserved by
 * Laravel's own built-in notification system (Illuminate\Notifications),
 * which this app already uses extensively via $user->notify(...), and
 * which Filament's admin panel also depends on for its own notification
 * bell. Using that name/table caused a real production outage — see
 * the 2026_03_10_080000_fix_notifications_table_conflict migration.
 */
class CustomerNotification extends Model
{
    protected $guarded = ['id'];
    protected $table = 'customer_notifications';

    const ACCOUNT_CREATED = 'account_created';
    const ACCOUNT_APPROVED = 'account_approved';
    const BAG_PURCHASED = 'bag_purchased';
    const SUBSCRIPTION_CREATED = 'subscription_created';
    const SUBSCRIPTION_CANCELLED = 'subscription_cancelled';
    const NEW_BOOKING = 'new_booking';
    const RIDER_ACCEPTED = 'rider_accepted';

    protected static function booted()
    {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('customer_notifications');
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
