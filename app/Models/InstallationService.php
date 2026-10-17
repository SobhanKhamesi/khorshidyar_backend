<?php

namespace App\Models;

use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallationService extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    // ---- purchase_source ----
    const INTERNAL = 1;
    const EXTERNAL = 2;

    // ---- status ----
    const TEMPORARY = 1; // موقت
    const FINAL = 2;  // نهایی

    // ---- نقش نصاب‌ها (باید با ستون type در installer_appointments هم‌خوان باشد) ----
    const ROLE_PANEL = 1;
    const ROLE_WIRING = 2;
    const ROLE_COMMISSIONING = 3;
    const ROLE_AFTER_SALE = 4;

    const ROLE_MAP = [
        'panel' => self::ROLE_PANEL,
        'wiring' => self::ROLE_WIRING,
        'commissioning' => self::ROLE_COMMISSIONING,
        'after_sale' => self::ROLE_AFTER_SALE,
    ];

    // باگ قبلی: کلید 'commissioning' دو بار تعریف شده بود و مقدار دومش
    // ('خدمات پس از فروش') مقدار اول را بی‌صدا بازنویسی می‌کرد؛ در نتیجه
    // اصلاً کلید 'after_sale' وجود نداشت و برچسب نصاب راه‌اندازی هم اشتباه بود.
    const ROLE_LABELS = [
        'panel' => 'نصاب پنل',
        'wiring' => 'نصاب سیم کشی',
        'commissioning' => 'نصاب راه اندازی',
        'after_sale' => 'خدمات پس از فروش',
    ];

    // این ثابت دیگر برای محاسبه‌ی خودکار ساعت پایان استفاده نمی‌شود، چون
    // کاربر خودش ساعت شروع و پایان هر نوبت را انتخاب می‌کند. فقط برای
    // مقادیر پیش‌فرض نمایشی (در صورت نیاز) نگه داشته شده است.
    const DEFAULT_APPOINTMENT_DURATION_MINUTES = 60;


    protected $fillable = [
        'customer_id',
        'purchase_source',
        'automation_id',
        'address',
        'contact_number',
        'description',
        'survey_date',
        'technical_expert_id',
        'status',
    ];

    protected $with = ['technicalExpert'];
    public $appends = ['jalali_date', 'jalali_survey_date'];

    protected $casts = [
        'survey_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function technicalExpert(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technical_expert_id');
    }

    public function installerAppointments(): HasMany
    {
        return $this->hasMany(InstallerAppointment::class);
    }

    public function installationServiceItems(): HasMany
    {
        return $this->hasMany(InstallationServiceItem::class);
    }

    public function afterSalesServices(): HasMany
    {
        return $this->hasMany(AfterSalesService::class);
    }

    public function scopeFinal($query)
    {
        return $query->where('status', self::FINAL);
    }

    public function getJalaliDateAttribute()
    {
        $jalaliDate = new Verta($this->created_at);
        return $jalaliDate->format('Y/m/d ساعت H:i');
    }

    public function getJalaliSurveyDateAttribute()
    {
        if ($this->survey_date != null) {
            $jalaliDate = new Verta($this->survey_date);
            return $jalaliDate->format('Y/m/d');
        } else {
            return null;
        }
    }
}
