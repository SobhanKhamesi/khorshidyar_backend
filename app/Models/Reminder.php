<?php

namespace App\Models;

use App\Traits\Uuids;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Morilog\Jalali\Jalalian;

class Reminder extends Model
{

    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';


    protected $table = 'reminders';


    const ACTIVE = 0;
    const COMPLETED = 1;

    const TYPE_GENERAL = 0;
    const TYPE_BIRTHDAY = 1;

    // ---- یادآوری‌های سیستمی (اعلان‌هایی که خود سامانه می‌سازد؛ App\Support\SystemReminders) ----
    const TYPE_INTAKE_FORM = 2;       // فرم اولیه توسط مشتری/سرنخ تکمیل شده
    const TYPE_CONTRACT_SIGNED = 3;   // قرارداد توسط مشتری امضا شده
    const TYPE_POOR_RATING = 4;       // نظرسنجی خدمات پس از فروش با رضایت «ضعیف»

    const SYSTEM_TYPES = [
        self::TYPE_INTAKE_FORM,
        self::TYPE_CONTRACT_SIGNED,
        self::TYPE_POOR_RATING,
    ];


    public $appends = [
        'jalali_date' , 'jalali_created', 'is_system'
    ];

    protected $fillable = [
        'title',
        'date',
        'status',
        'description',
        'user_id',
        'type',
        'customer_id',
        'source_id',
        'birthday_sms_sent',
    ];

    public function getIsSystemAttribute(): bool
    {
        return in_array((int)$this->type, self::SYSTEM_TYPES, true);
    }

    public function scopeSystem($query)
    {
        return $query->whereIn('type', self::SYSTEM_TYPES);
    }

    public function scopeNotSystem($query)
    {
        return $query->whereNotIn('type', self::SYSTEM_TYPES);
    }


    public function getJalaliDateAttribute()
    {
        $jalaliDate = new Verta($this->date);
        return $jalaliDate->format('Y/m/d');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * تاریخ وقوع بعدی یک رویداد سالانه (مثل تولد) را بر اساس تاریخ پایه محاسبه می‌کند.
     * اگر روز/ماه مربوطه در سال جاری گذشته باشد، سال بعد برگردانده می‌شود.
     */
    public static function nextYearlyOccurrence($date)
    {
        $base = Carbon::parse($date);
        $now = Carbon::now();

        $next = $base->copy()->year($now->year);
        if ($next->lt($now->copy()->startOfDay())) {
            $next->addYear();
        }

        return $next;
    }

    public function getSabtKonandeAttribute()
    {
        return $this->user?->name;

    }


    public function getJalaliCreatedAttribute()
    {
        // created_at به وقت تهران (timezone برنامه) ذخیره می‌شود؛ نیازی به افزودن اختلاف ساعت نیست
        if (!$this->created_at)
            return null;
        return Jalalian::fromCarbon($this->created_at)->format('Y/m/d ساعت H:i');
    }


}
