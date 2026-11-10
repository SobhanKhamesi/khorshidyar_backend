<?php

namespace App\Models;

use App\Traits\Uuids;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
    const CUSTOMER = 3;  // ثبت شده از مشتری (فرم اولیه‌ای که خود مشتری تکمیل کرده)

    protected $fillable = [
        'status' ,  // وضعیت
        'user_id' ,  // ثبت کننده
        'customer_id' ,  // مشتری یا سرنخ
        'referrer_percent' ,  // درصد معرف از این فروش
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
        'description' ,  // توضیحات درخواست
        'invoice_description' ,  // توضیحات پیش فاکتور (زیر اقلام در پرینت)
        'gross_sale_amount' ,  // مبلغ فروش در لحظه‌ی فروش موفق
        'gross_purchase_cost' ,  // جمع قیمت خرید اقلام در لحظه‌ی فروش موفق
        'gross_profit' ,  // سود ناخالص اولیه (ثابت)
        'gross_profit_at' ,
    ];

    // اطلاعات سود فقط برای مدیر نمایش داده می‌شود (makeVisible در کنترلر)
    const PROFIT_FIELDS = ['gross_sale_amount', 'gross_purchase_cost', 'gross_profit', 'gross_profit_at'];
    protected $hidden = self::PROFIT_FIELDS;
    public $with = ['customer', 'orders', 'expert', 'failure', 'comments', 'contractItems', 'payments', 'cheques', 'user', 'intakeForm', 'contract'];
    public $appends = ['jalali_date', 'jalali_visit_date', 'sabtKonande', 'referrer_income'];

    protected $casts = [
        'referrer_percent' => 'integer',
        'gross_sale_amount' => 'integer',
        'gross_purchase_cost' => 'integer',
        'gross_profit' => 'integer',
        'gross_profit_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // هر مسیری که اتوماسیون را به «فروش موفق» ببرد، سود ناخالص اولیه را ثبت می‌کند
        static::updated(function (Automation $automation) {
            if ($automation->wasChanged('status') && (int)$automation->status === self::SUCCESS)
                $automation->snapshotGrossProfit();
        });
    }

    /**
     * ثبت سود ناخالص اولیه در لحظه‌ی فروش موفق؛ فقط یک بار ثبت می‌شود و
     * اصلاحیه‌ی فاکتور، تغییر قیمت محصولات یا محاسبات سرویس نصب آن را تغییر نمی‌دهند.
     * سود ناخالص = مبلغ نهایی (دستی) − جمع (تعداد × قیمت خرید) اقلام فاکتور
     */
    public function snapshotGrossProfit(): void
    {
        if ($this->gross_profit_at !== null)
            return;

        $orders = $this->orders()->get();
        $cost = (int)round($orders->sum(fn(Order $o) => (float)$o->count * (int)$o->purchase_price));
        $sale = $this->final_price !== null
            ? (int)$this->final_price
            : (int)round($orders->sum(fn(Order $o) => (float)$o->count * (int)$o->price));

        $this->update([
            'gross_sale_amount' => $sale,
            'gross_purchase_cost' => $cost,
            'gross_profit' => $sale - $cost,
            'gross_profit_at' => now(),
        ]);
    }

    // سهم معرف از این پروژه (تومان) = مبلغ نهایی × درصد معرف همین پروژه
    public function getReferrerIncomeAttribute()
    {
        if ($this->referrer_percent === null || $this->final_price === null)
            return null;

        return (int)round((float)$this->final_price * $this->referrer_percent / 100);
    }


    public function homeSurvey()  // فرم بازدید خانگی
    {
        return $this->hasOne(AutomationHomeSurvey::class, 'automation_id');
    }

    public function contract() // قرارداد (مرحله مذاکره و قرارداد)
    {
        return $this->hasOne(AutomationContract::class, 'automation_id');
    }

    public function customerSurvey() // فرم بازدید ثبت شده از مشتری
    {
        return $this->hasOne(AutomationCustomerSurvey::class, 'automation_id');
    }

    // آخرین لینک فرم اولیه‌ای که برای مشتری ارسال شده (وضعیت تکمیل و مشاهده)
    public function intakeForm()
    {
        // شناسه‌ها uuid تصادفی هستند؛ «آخرین» بر اساس زمان ایجاد تعیین می‌شود نه بزرگ‌ترین id
        return $this->hasOne(CustomerIntakeForm::class, 'automation_id')->latestOfMany('created_at');
    }

    // سرویس نصبی که پس از فروش موفق برای این پروژه ارجاع شده (هر فروش موفق یک سرویس نصب دارد)
    public function installationService()
    {
        return $this->hasOne(InstallationService::class, 'automation_id')->latestOfMany('created_at');
    }

    public function industrialSurvey() // فرم بازدید صنعتی
    {
        return $this->hasOne(AutomationIndustrialSurvey::class, 'automation_id');
    }

    public function getSabtKonandeAttribute()
    {
        return $this->user?->name;
    }

    public function failure()
    {
        return $this->belongsTo(FailureCategory::class, 'failure_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withStats();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function expert()  // کارشناس فنی
    {
        return $this->belongsTo(User::class, 'expert_id');
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

    /**
     * شماره فاکتور بعدی (بازه ۱۰۰ تا ۹۹۹۹). باید داخل تراکنش صدا زده شود تا
     * قفل ردیف‌ها مانع از ثبت شماره تکراری در درخواست‌های هم‌زمان شود.
     */
    public static function nextInvoiceNumber(): int
    {
        $max = static::query()
            ->withoutEagerLoads()
            ->whereNotNull('invoice_number')
            ->whereRaw('CAST(invoice_number AS UNSIGNED) between 100 and 9999')
            ->lockForUpdate()
            ->max(DB::raw('CAST(invoice_number AS UNSIGNED)'));

        return ($max ?? 99) + 1;
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
