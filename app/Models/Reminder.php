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


    public $appends = [
        'jalali_date' , 'jalali_created'
    ];

    protected $fillable = [
        'title',
        'date',
        'status',
        'description',
        'user_id',
        'type',
        'customer_id',
        'birthday_sms_sent',
    ];


    public function getJalaliDateAttribute()
    {
        $jalaliDate = new Verta($this->date);
        return $jalaliDate->format('Y/m/d');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function customer()
    {
        return $this->hasOne(Customer::class, 'id', 'customer_id');
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
        return $this->user->name;

    }


    public function getJalaliCreatedAttribute()
    {
        $createdDateTime = $this->attributes['created_at'];
        $carbonDateTime = Carbon::parse($createdDateTime, 'UTC')->addHours(3)->addMinutes(30);
        $jalaliDateTime = Jalalian::fromCarbon($carbonDateTime);
        return $jalaliDateTime->format('Y/m/d ساعت H:i');
    }


}
