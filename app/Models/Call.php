<?php

namespace App\Models;

use App\Traits\Uuids;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Morilog\Jalali\Jalalian;

class Call extends Model
{

    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';


    protected $table = 'calls';


    protected $fillable = [
        'customer_id',
        'subject',
        'type_call',
        'Deal',
        'date',
        'time',
        're_follow_date',
        're_follow_time',
        're_follow_subject',
        'description',
        'contacted',
        'user_id'
    ];


    public $appends = ['jalali_full_date', 'jalali_re_follow_full_date','jalali_created',
        'jalali_re_follow_date', 'jalali_re_follow_time', 'jalali_date', 'SabtKonande'];
    public $with = ['customer'];


    public function customer()
    {
        return $this->hasOne(Customer::class, 'id', 'customer_id');
    }


    public function getJalaliFullDateAttribute()
    {
        $jalaliDate = new Verta($this->date . ' ' . $this->time);
        return $jalaliDate->format('Y/m/d ساعت H:i');
    }

    public function getJalaliReFollowFullDateAttribute()
    {

        if ($this->re_follow_date != null && $this->re_follow_time != null) {
            $jalaliDate = new Verta($this->re_follow_date . ' ' . $this->re_follow_time);
            return $jalaliDate->format('Y/m/d ساعت H:i');
        } else if ($this->re_follow_date != null) {
            $jalaliDate = new Verta($this->re_follow_date);
            return $jalaliDate->format('Y/m/d ساعت H:i');
        } else {
            return null;
        }
    }

    public function getJalaliDateAttribute()
    {
        $jalaliDate = new Verta($this->date);
        return $jalaliDate->format('Y/m/d');
    }


    public function getJalaliReFollowDateAttribute()
    {
        if ($this->re_follow_date != null) {
            $jalaliDate = new Verta($this->re_follow_date);
            return $jalaliDate->format('Y/m/d');
        } else {
            return null;
        }
    }


    public function getjalalirefollowtimeAttribute()
    {
        if ($this->re_follow_time != null) {
            $jalaliTime = new Verta($this->re_follow_time);
            return $jalaliTime->format('H:i');
        } else {
            return null;
        }
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function getSabtKonandeAttribute()
    {
        return $this->user->name;
    }

    public function getJalaliCreatedAttribute()
    {
        $jalaliDate = new Verta($this->attributes['created_at']);
        return $jalaliDate->format('Y/m/d ساعت H:i');
    }

}
