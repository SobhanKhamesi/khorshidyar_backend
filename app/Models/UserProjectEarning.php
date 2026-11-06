<?php

namespace App\Models;

use App\Services\ProjectProfitService;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * دریافتی کاربر (نصاب/کارشناس) از یک پروژه (سرویس نصب)
 */
class UserProjectEarning extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    const MONTH_NAMES = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    // ---- work_type: نوع کاری که دستمزد بابت آن است ----
    const WORK_PROJECT = 'project';             // نصب کل پروژه (حالت یک نصاب)
    const WORK_PANEL = 'panel';                 // نصب پنل
    const WORK_WIRING = 'wiring';               // سیم کشی
    const WORK_COMMISSIONING = 'commissioning'; // راه اندازی
    const WORK_REVIEW = 'review';               // بررسی کارشناس فنی

    const WORK_LABELS = [
        self::WORK_PROJECT => 'نصب کل پروژه',
        self::WORK_PANEL => 'نصب پنل',
        self::WORK_WIRING => 'سیم کشی',
        self::WORK_COMMISSIONING => 'راه اندازی',
        self::WORK_REVIEW => 'بررسی کارشناس فنی',
    ];

    protected $fillable = [
        'user_id',
        'installation_service_id',
        'work_type',
        'is_auto',
        'amount',
        'date',
        'is_settled',
        'settled_at',
        'description',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'date' => 'date',
        'settled_at' => 'date',
        'is_settled' => 'boolean',
        'is_auto' => 'boolean',
    ];

    protected $appends = ['jalali_date', 'jalali_settled_at', 'jalali_year', 'jalali_month', 'work_label'];

    // دستمزدها بخشی از هزینه‌های پروژه هستند؛ با هر تغییر، سود نهایی پروژه به‌روز می‌شود
    protected static function booted(): void
    {
        $refresh = fn(UserProjectEarning $earning) => ProjectProfitService::refreshById($earning->installation_service_id);
        static::saved($refresh);
        static::deleted($refresh);
    }

    public function getWorkLabelAttribute(): ?string
    {
        return self::WORK_LABELS[$this->work_type] ?? null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function installationService(): BelongsTo
    {
        return $this->belongsTo(InstallationService::class);
    }

    public function getJalaliDateAttribute(): ?string
    {
        return $this->date ? (new Verta($this->date))->format('Y/m/d') : null;
    }

    public function getJalaliSettledAtAttribute(): ?string
    {
        return $this->settled_at ? (new Verta($this->settled_at))->format('Y/m/d') : null;
    }

    public function getJalaliYearAttribute(): ?int
    {
        return $this->date ? (new Verta($this->date))->year : null;
    }

    public function getJalaliMonthAttribute(): ?int
    {
        return $this->date ? (new Verta($this->date))->month : null;
    }
}
