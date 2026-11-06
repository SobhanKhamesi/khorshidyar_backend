<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laratrust\Traits\HasRolesAndPermissions;
class User extends Authenticatable
{

    use HasFactory, HasApiTokens, Notifiable,HasRolesAndPermissions;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';
    public $timestamps = false;

    const ADMIN = 0;
    const KARSHENAS_FANI = 1;
    const NASSAB = 2;
    const KARSHENAS_FOROSH = 3;
    const MODIR_FOROSH = 3;


    const ACTIVE = 0;
    const INACTIVE = 1;

    // سمت کاربر (role)
    const ROLE_LABELS = [
        self::ADMIN => 'ادمین',
        self::KARSHENAS_FANI => 'کارشناس فنی',
        self::NASSAB => 'نصاب',
        self::KARSHENAS_FOROSH => 'کارشناس فروش',
        4 => 'مدیر فروش',
    ];

    protected $fillable = [
        'name', 'username', 'mobile', 'password', 'status', 'role', 'profile', 'emza',
        'national_code', 'mobiles', 'phone', 'address',
    ];

    protected $casts = [
        'mobiles' => 'array',
    ];

    protected static function booted(): void
    {
        // اگر فقط شماره‌ی اصلی (mobile) تغییر کند (مثلاً از صفحه کاربران)، اولین شماره‌ی لیست هم به‌روز می‌شود
        static::saving(function (User $user) {
            if (!$user->isDirty('mobile') || $user->isDirty('mobiles'))
                return;

            $mobiles = $user->mobiles ?? [];
            $old = $user->getOriginal('mobile');
            $mobiles = array_values(array_filter($mobiles, fn($m) => $m !== $old && $m !== $user->mobile));
            if ($user->mobile)
                array_unshift($mobiles, $user->mobile);
            $user->mobiles = $mobiles;
        });
    }

    public function projectEarnings()
    {
        return $this->hasMany(UserProjectEarning::class);
    }

    public function getRoleLabelAttribute(): ?string
    {
        return self::ROLE_LABELS[$this->role] ?? null;
    }

    /**
     * ذخیره‌ی لیست شماره‌های همراه؛ شماره‌ی اول در ستون mobile (برای پیامک‌ها) هم قرار می‌گیرد
     */
    public function syncMobiles(array $mobiles): void
    {
        $mobiles = array_values(array_unique(array_filter(array_map('trim', $mobiles))));
        $this->mobiles = $mobiles;
        $this->mobile = $mobiles[0] ?? null;
    }

    protected $hidden = [
        'password'
    ];

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = bcrypt($value);
    }
}
