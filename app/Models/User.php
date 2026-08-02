<?php

namespace App\Models;

use App\Models\Role;
use App\Models\Setting;
use Carbon\Carbon;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Guava\Sqids\Facades\Sqids;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail, Auditable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use \OwenIt\Auditing\Auditable;
    use HasFactory, Notifiable, HasApiTokens, HasRoles;
    use SoftDeletes;

    const ACTIVE = 'active';
    const INACTIVE = 'inactive';
    const PENDING = 'pending';
    const ONBOARDING = 'onboarding';
    const REJECTED = 'rejected';

    const CUSTOMER = 'customer';
    const RIDER = 'rider';
    const MERCHANT = 'merchant';

    protected $appends = ['avatar_url'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'country_code_mobile',
        'mobile_no',
        'gender',
        'dob',
        'email_verified_at',
        'status',
        'rejected_reason',
        'is_active',
        'id_type',
        'icno',
        'address_line_1',
        'address_line_2',
        'country_id',
        'state_id',
        'postcode',
        'city',
        'latitude',
        'longitude',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'dob' => 'date',
        ];
    }

    /**
     * [getDobAttribute description]
     * @param  [type] $value [description]
     * @return [type]        [description]
     */
    public function getDobAttribute($value)
    {
        return $value
            ? Carbon::parse($value)->format('Y-m-d')
            : null;
    }    

    /**
     * [booted description]
     * @return [type] [description]
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            $hashids = Sqids::make()->alphabet(config('services.sqids.alphabet'))->minLength(15)->salt('users');
            do {
                $uniqueValue = strtotime(now()) . random_int(1, 999999);
                $hashslug = $hashids->encode([$uniqueValue]);
            } while (self::where('hashslug', $hashslug)->exists());
            $model->hashslug = $hashslug;
        });
    }

    /**
     * [superAdminEmail description]
     * @return [type] [description]
     */
    public static function superAdminEmail(): ?string
    {
        return Setting::find(1)?->admin_email;
    }

    /**
     * [getAvatarUrlAttribute description]
     * @return [type] [description]
     */
    public function getAvatarUrlAttribute()
    {
        return $this->avatar ? Storage::disk('s3')->url($this->avatar) : null;
    }

    /**
     * [impersonate description]
     * @return [type] [description]
     */
    public function impersonate()
    {
        $user = Self::firstWhere('hashslug', auth()->user()->hashslug);
        request()->session()->put('impersonate_id', auth()->user()->id);
        auth()->loginUsingId($user->id);
        return redirect()->route('filament.admin.resources.users.index');
    }

    /**
     * [canAccessPanel description]
     * @param  Panel  $panel [description]
     * @return [type]        [description]
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin']) && $this->hasVerifiedEmail();
    }

    /**
     * [merchant description]
     * @return [type] [description]
     */
    public function merchant()
    {
        return $this->hasOne('App\Models\Merchant', 'user_id', 'id');
    }

    /**
     * [rider description]
     * @return [type] [description]
     */
    public function rider()
    {
        return $this->hasOne('App\Models\Rider', 'user_id', 'id');
    }

    /**
     * [country description]
     * @return [type] [description]
     */
    public function country()
    {
        return $this->belongsTo(\App\Models\Country::class);
    }

    /**
     * [state description]
     * @return [type] [description]
     */
    public function state()
    {
        return $this->belongsTo(\App\Models\State::class);
    }

    /**
     * [scopeActive description]
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopeActive($query)
    {
        return $query->where('status', Self::ACTIVE);
    }

    /**
     * [addresses description]
     * @return [type] [description]
     */
    public function addresses()
    {
        return $this->hasMany('App\Models\Address', 'user_id')->where('status', 'active');
    }

    /**
     * [bags description]
     * @return [type] [description]
     */
    public function bags()
    {
        return $this->hasMany('App\Models\Bag', 'user_id')->where('status_payment', '!=', 'pending');
    }

    /**
     * [bag_scans description]
     * @return [type] [description]
     */
    public function bag_scans()
    {
        return $this->hasMany('App\Models\Qrcode', 'user_id', 'id')->whereNotNull('user_id');
    }

    /**
     * [bag_purchases description]
     * @return [type] [description]
     */
    public function bag_purchases()
    {
        return $this->hasMany('App\Models\Bag', 'user_id')->where('status_payment', 'paid');
    }

    /**
     * [qrcodes description]
     * @return [type] [description]
     */
    public function qrcodes()
    {
        return $this->hasMany('App\Models\Qrcode', 'user_id', 'id')->whereNotNull('user_id');
    }

    /**
     * [activities description]
     * @return [type] [description]
     */
    public function activities()
    {
        if (auth()->user()->roles->pluck('name')[0] == 'rider') {
            return $this->hasMany('App\Models\Activity', 'user_id')->where('status', 'active')->where(['user_type' => 'rider'])->latest();
        }
        else if (auth()->user()->roles->pluck('name')[0] == 'merchant') {
            return $this->hasMany('App\Models\Activity', 'user_id')->where('status', 'active')->where(['user_type' => 'merchant'])->latest();
        }
        else {
            return $this->hasMany('App\Models\Activity', 'user_id')->where('status', 'active')->where(['user_type' => 'customer'])->latest();            
        }
    }

    /**
     * [activity_cancels description]
     * @return [type] [description]
     */
    public function activity_cancels()
    {
        if (auth()->user()->roles->pluck('name')[0] == 'rider') {
            return $this->hasMany('App\Models\Activity', 'rider_id')->where('status', 'active')->where(['user_type' => 'rider'])->latest();
        }
        else if (auth()->user()->roles->pluck('name')[0] == 'merchant') {
            return $this->hasMany('App\Models\Activity', 'merchant_id')->where('status', 'active')->where(['user_type' => 'merchant'])->latest();
        }
    }

    /**
     * [wallet description]
     * @return [type] [description]
     */
    public function wallet()
    {
        return $this->hasOne('App\Models\Commission', 'user_id')->whereIn('status', ['pending', 'paid']);
    }

    /**
     * [tickets description]
     * @return [type] [description]
     */
    public function tickets()
    {
        return $this->hasMany('App\Models\Ticket', 'user_id', 'id');
    }

    /**
     * [assign_jobs description]
     * @return [type] [description]
     */
    public function assign_jobs()
    {
        // return $this->hasOne('App\Models\Rider', 'user_id', 'id');
        return $this->hasMany('App\Models\AssignJob', 'user_id', 'id');
    }


    /**
     * [subscribe description]
     * @return [type] [description]
     */
    public function subscribe()
    {
        return $this->hasOne('App\Models\Subscription', 'user_id', 'id')
        ->where('status', 'active')
        ->whereDate('start_date', '<=', Carbon::now()->format('Y-m-d'))
        ->whereDate('end_date', '>=', Carbon::now()->format('Y-m-d'));     
    }

    /**
     * [pending_subscribe description]
     * @return [type] [description]
     */
    public function pending_subscribe()
    {
        return $this->hasOne('App\Models\Subscription', 'user_id')->where('status', 'pending');
    }

    /**
     * [unsubscribes description]
     * @return [type] [description]
     */
    public function unsubscribes()
    {
        return $this->hasMany('App\Models\Unsubscribe', 'user_id', 'id');    
    }

    /**
     * [bookings description]
     * @return [type] [description]
     */
    public function bookings()
    {
        return $this->hasMany('App\Models\Booking', 'user_id', 'id')->where('status', 'active');
    }

    /**
     * [mobile_change description]
     * @return [type] [description]
     */
    public function mobile_change()
    {
        return $this->hasOne('App\Models\MobileChange', 'user_id');
    }

    /**
     * [scanned_qrcodes description]
     * @return [type] [description]
     */
    public function scanned_qrcodes()
    {
        return $this->hasMany('App\Models\Qrcode', 'user_id')->where(['status' => 'scanned']);
    }

    /**
     * [covered_locations description]
     * @return [type] [description]
     */
    public function covered_locations()
    {
        return $this->hasMany('App\Models\CityUser', 'user_id');
    }

    /**
     * [birthday description]
     * @return [type] [description]
     */
    public function birthday()
    {
        return $this->hasOne(\App\Models\BirthdayUser::class, 'user_id');
    }

    /**
     * [hasTakenBirthdayThisYear description]
     * @return boolean [description]
     */
    public function hasTakenBirthdayThisYear(): bool
    {
        return $this->birthday()
            ->whereYear('created_at', now()->year)
            ->whereMonth('date', $this->dob->month)
            ->exists();
    }

    /**
     * [bag_free description]
     * @return [type] [description]
     */
    public function bag_free()
    {
        return $this->hasOne('App\Models\Bag', 'user_id')->where(['status' => 'processing', 'status_payment' => 'paid']);
    }


}



