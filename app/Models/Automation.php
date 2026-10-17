<?php

namespace App\Models;

use App\Traits\Uuids;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Automation extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'automation';

    //status
    const REQUEST = 0; // درخواست ثبت شده
    const PRE_INVOICE = 1;  // پیش فاکتور ثبت شده
    const NEGOTIATION = 2;  // مذاکره و قرارداد ثبت شده
    const SUCCESS = 3;  // فروش موفق

    const FAILURE = -1;  // فروش های با شکست مواجه شده


    // survey_type
    const HOME = 1;  // خانگی
    const INDUSTRIAL = 2;  // صنعتی

    protected $fillable = [
        'status' ,  // وضعیت
        'user_id' ,  // ثبت کننده
        'customer_id' ,  // مشتری یا سرنخ
        'expert_id' ,  // کارشناس فنی
        'address' ,  // آدرس
        'invoice_number' ,  // شماره فاکتور
        'visit_date' ,  // تاریخ بازدید
        'negotiator' ,  // فرد مذاکره کننده
        'send_sms' ,  // boolean
        'wage' ,  // اجرت
        'failure_id' ,  // دلیل شکست
        'final_price' ,  // مبلغ نهایی
        'survey_type' ,  // نوع بازدید
        'description' ,
    ];
    public $with = ['customer', 'orders', 'expert','failure','comments','contractItems','payments','cheques'];
    public $appends = ['jalali_date', 'jalali_visit_date', 'sabtKonande'];

    public function homeSurvey()  // فرم بازدید خانگی
    {
        return $this->hasOne(AutomationHomeSurvey::class, 'automation_id');
    }

    public function industrialSurvey() // فرم بازدید صنعتی
    {
        return $this->hasOne(AutomationIndustrialSurvey::class, 'automation_id');
    }

    public function getSabtKonandeAttribute()
    {
        return $this->user->name;
    }

    public function failure()
    {
        return $this->hasOne(FailureCategory::class, 'id', 'failure_id');
    }
    public function customer()
    {
        return $this->hasOne(Customer::class, 'id', 'customer_id');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function expert()  // کارشناس فنی
    {
        return $this->hasOne(User::class, 'id', 'expert_id');
    }
    public function comments()
    {
        return $this->hasMany(AutomatonComment::class, 'automation_id');
    }
    public function contractItems()
    {
        return $this->hasMany(AutomationContractItem::class);
    }

    public function payments()
    {
        return $this->hasMany(AutomationPayment::class);
    }

    public function cheques()
    {
        return $this->hasMany(AutomationCheque::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function getJalaliDateAttribute()
    {
        $jalaliDate = new Verta($this->created_at);
        return $jalaliDate->format('Y/m/d ساعت H:i');
    }

    public function getJalaliVisitDateAttribute()
    {
        if ($this->visit_date != null) {
            $jalaliDate = new Verta($this->visit_date);
            return $jalaliDate->format('Y/m/d');
        } else {
            return null;
        }
    }
}
