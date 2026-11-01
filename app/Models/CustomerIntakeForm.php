<?php

namespace App\Models;

use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * لینک فرم اولیه‌ای که برای مشتری/سرنخ پیامک می‌شود (وضعیت ارسال، تکمیل و مشاهده).
 * پاسخ‌ها به‌عنوان فرم بازدید نوع «ثبت شده از مشتری» در AutomationCustomerSurvey ذخیره می‌شوند.
 */
class CustomerIntakeForm extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    const SMS_TEMPLATE_TITLE = 'فرم اولیه';

    const DEFAULT_SMS_TEXT = "از قطعی ها و هزینه سنگین قبض برق خسته شدید!؟\n\n"
        . "الان وقتش رسیده که از خورشید کسب درآمد کنید!\n\n"
        . "✅ کاهش نگرانی از قطعی برق\n\n"
        . "✅ درآمدزایی از انرژی خورشیدی\n\n"
        . "✅ امکان استفاده از وام ۴۰۰ میلیون تومانی با نرخ ۱۴٪ و بازپرداخت ۵ ساله\n\n"
        . "📝برای برسی شرایط و دریافت مشاوره رایگان  فرم درخواست را تکمیل کنید.\n\n"
        . "{link}";

    protected $fillable = [
        'token',
        'customer_id',
        'automation_id',
        'sent_by',
        'sms_sent',
        'submitted_at',
        'seen_at',
    ];

    protected $casts = [
        'sms_sent' => 'boolean',
        'submitted_at' => 'datetime',
        'seen_at' => 'datetime',
    ];

    protected $hidden = ['token'];

    public $appends = ['is_submitted', 'jalali_submitted_at', 'jalali_created_at'];

    // متن شرایط دریافت وام که پیش از سوالات نمایش داده می‌شود
    public static function intro(): string
    {
        return "📌 شرایط دریافت وام\n"
            . "لطفاً پیش از ثبت درخواست، از دارا بودن شرایط زیر اطمینان حاصل فرمایید:\n"
            . "✅ داشتن دسته‌چک صیادی (بانک صادرکننده تفاوتی ندارد)\n"
            . "✅ نداشتن چک برگشتی\n"
            . "✅ نداشتن اقساط معوقه مربوط به وام‌های شخصی یا وام‌هایی که ضمانت آن‌ها را بر عهده گرفته‌اید.\n"
            . "✅ داشتن حساب فعال در بانک ملت\n"
            . "از همکاری و اعتماد شما سپاسگزاریم.";
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::random(40);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    public function link(): string
    {
        return rtrim(config('services.frontend_url'), '/') . '/request/' . $this->token;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function automation()
    {
        return $this->belongsTo(Automation::class, 'automation_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function getIsSubmittedAttribute(): bool
    {
        return $this->submitted_at !== null;
    }

    public function getJalaliSubmittedAtAttribute()
    {
        return $this->submitted_at ? (new Verta($this->submitted_at))->format('Y/m/d ساعت H:i') : null;
    }

    public function getJalaliCreatedAtAttribute()
    {
        return $this->created_at ? (new Verta($this->created_at))->format('Y/m/d ساعت H:i') : null;
    }
}
