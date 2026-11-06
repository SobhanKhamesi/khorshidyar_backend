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

    // ---- installer_mode ----
    const MODE_SINGLE = 1;   // کل پروژه نصب به یک نصاب ارجاع می‌شود (در نوبت نقش panel ذخیره می‌شود)
    const MODE_SEPARATE = 2; // نصاب پنل، سیم کشی و راه اندازی جداگانه انتخاب می‌شوند

    // ---- status ----
    // فقط وقتی سرویس به مرحله‌ی «تکمیل نهایی» برسد FINAL می‌شود؛ مرحله‌ی دقیق در ستون stage است
    const TEMPORARY = 1; // موقت
    const FINAL = 2;  // نهایی

    // ---- stage (گردش کار سرویس نصب) ----
    const STAGE_DRAFT = 0;               // پیش‌نویس (هنوز به نصاب ارجاع نشده)
    const STAGE_ASSIGNED = 1;            // ارجاع شده به نصاب - در حال نصب
    const STAGE_INSTALLER_SUBMITTED = 2; // نصاب اقلام مصرفی را ثبت و به مدیر ارجاع داده
    const STAGE_UNDER_REVIEW = 3;        // ارسال شده برای بررسی کارشناس فنی
    const STAGE_NEEDS_FIX = 4;           // برگشت به نصاب برای رفع عیوب
    const STAGE_APPROVED = 5;            // تایید شده توسط کارشناس فنی
    const STAGE_COMPLETED = 6;           // تکمیل نهایی

    const STAGE_LABELS = [
        self::STAGE_DRAFT => 'پیش‌نویس',
        self::STAGE_ASSIGNED => 'ارجاع به نصاب - در حال نصب',
        self::STAGE_INSTALLER_SUBMITTED => 'تکمیل توسط نصاب - در انتظار ارجاع به کارشناس',
        self::STAGE_UNDER_REVIEW => 'در حال بررسی کارشناس فنی',
        self::STAGE_NEEDS_FIX => 'برگشت به نصاب برای رفع عیوب',
        self::STAGE_APPROVED => 'تایید کارشناس - آماده تکمیل نهایی',
        self::STAGE_COMPLETED => 'تکمیل نهایی',
    ];

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
        'installer_mode',
        'automation_id',
        'address',
        'contact_number',
        'description',
        'survey_date',
        'technical_expert_id', // کارشناس فنی بررسی کننده‌ی پروژه پس از تکمیل نصاب
        'status',
        'stage',
        'completed_at',
        'final_profit',        // سود نهایی پروژه (توسط ProjectProfitService محاسبه می‌شود)
        'wage_generated_keys', // دستمزدهای خودکاری که قبلاً ایجاد شده‌اند تا پس از حذف دستی دوباره ساخته نشوند
    ];

    protected $with = ['technicalExpert'];
    public $appends = ['jalali_date', 'jalali_survey_date', 'stage_label'];

    // اطلاعات سود فقط برای مدیر نمایش داده می‌شود
    protected $hidden = ['final_profit', 'wage_generated_keys'];

    protected $casts = [
        'survey_date' => 'date',
        'completed_at' => 'datetime',
        'stage' => 'integer',
        'installer_mode' => 'integer',
        'final_profit' => 'integer',
        'wage_generated_keys' => 'array',
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

    public function requiredItems(): HasMany // اقلام پیش‌نیاز
    {
        return $this->hasMany(InstallationServiceItem::class)->where('type', InstallationServiceItem::TYPE_REQUIRED);
    }

    public function usedItems(): HasMany // اقلام مصرف شده توسط نصاب
    {
        return $this->hasMany(InstallationServiceItem::class)->where('type', InstallationServiceItem::TYPE_USED);
    }

    public function financeItems(): HasMany // هزینه‌ها و دریافتی‌های جانبی
    {
        return $this->hasMany(InstallationServiceFinanceItem::class)->orderBy('created_at');
    }

    public function earnings(): HasMany // دستمزد افراد از این پروژه
    {
        return $this->hasMany(UserProjectEarning::class)->orderBy('created_at');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(InstallationServiceHistory::class)->orderBy('created_at');
    }

    public function activeAppointments(): HasMany
    {
        return $this->hasMany(InstallerAppointment::class)->where('status', '!=', InstallerAppointment::CANCELED);
    }

    public function getStageLabelAttribute()
    {
        return self::STAGE_LABELS[$this->stage] ?? null;
    }

    public function isAssignedTo($userId): bool
    {
        return $this->activeAppointments()->where('user_id', $userId)->exists();
    }

    /**
     * نصاب خود پروژه: نصاب کل پروژه (حالت یک نصاب) یا نصاب پنل؛ در غیر این صورت اولین نصاب فعال
     */
    public function projectInstaller(): ?User
    {
        $appointments = $this->activeAppointments()->with('installer')->orderBy('type')->get();

        return ($appointments->firstWhere('type', self::ROLE_PANEL) ?? $appointments->first())?->installer;
    }

    // عنوان خوانا برای نمایش به جای شناسه
    public function getTitleAttribute(): string
    {
        return 'سرویس نصب ثبت شده در ' . (new Verta($this->created_at))->format('Y/m/d')
            . ($this->stage_label ? ' (' . $this->stage_label . ')' : '');
    }

    /**
     * سرویس‌هایی که منتظر اقدام کاربر هستند (تکمیل نهایی شده‌ها هیچ‌وقت شمرده نمی‌شوند)
     *  - نصاب: ارجاع شده یا برگشت خورده‌ی خودش
     *  - کارشناس فنی: سرویس‌های در حال بررسی که به او سپرده شده
     *  - مدیر: پیش‌نویس، تکمیل شده توسط نصاب، تایید شده (منتظر تکمیل نهایی) و هر سرویس در حال بررسی
     */
    public function scopeAwaitingActionBy($query, User $user)
    {
        if ($user->role == User::NASSAB) {
            return $query->whereIn('stage', [self::STAGE_ASSIGNED, self::STAGE_NEEDS_FIX])
                ->whereHas('activeAppointments', fn($q) => $q->where('user_id', $user->id));
        }

        return $query->where(function ($q) use ($user) {
            $q->where(fn($q) => $q->where('stage', self::STAGE_UNDER_REVIEW)->where('technical_expert_id', $user->id));

            if ($user->role == User::ADMIN) {
                $q->orWhereIn('stage', [
                    self::STAGE_DRAFT, self::STAGE_INSTALLER_SUBMITTED, self::STAGE_UNDER_REVIEW, self::STAGE_APPROVED,
                ]);
            }
        });
    }

    public function logStage(int $stage, ?string $description = null): void
    {
        $this->histories()->create([
            'user_id' => auth()->id(),
            'stage' => $stage,
            'description' => $description,
        ]);
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
