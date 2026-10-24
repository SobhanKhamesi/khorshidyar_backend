<?php

namespace App\Models;

use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AfterSalesService extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    // ---- status ----
    const STATUS_PENDING = 1;      // در انتظار پیگیری
    const STATUS_IN_PROGRESS = 2;  // در حال پیگیری
    const STATUS_COMPLETED = 3;    // تکمیل شده
    const STATUS_CANCELED = 4;     // لغو شده

    const STATUS_LABELS = [
        self::STATUS_PENDING => 'در انتظار پیگیری',
        self::STATUS_IN_PROGRESS => 'در حال پیگیری',
        self::STATUS_COMPLETED => 'تکمیل شده',
        self::STATUS_CANCELED => 'لغو شده',
    ];

    // ---- source ----
    const SOURCE_MANUAL = 1;                 // ثبت دستی توسط کاربر
    const SOURCE_INSTALLATION_REMINDER = 2;  // یادآوری خودکار سرویس نصب

    const SOURCE_LABELS = [
        self::SOURCE_MANUAL => 'ثبت دستی',
        self::SOURCE_INSTALLATION_REMINDER => 'یادآوری خودکار سرویس نصب',
    ];

    // مدت زمان (روز) پس از ثبت نهایی سرویس نصب تا ایجاد یادآوری خودکار
    const REMINDER_AFTER_DAYS = 3;

    protected $fillable = [
        'customer_id',
        'installation_service_id',
        'service_type_id',
        'responsible_user_id',
        'problem',
        'request_date',
        'follow_up_date',
        'status',
        'source',
        'resolution_description',
        'completed_at',
    ];

    protected $with = ["ratings" , "serviceType"];

    public $appends = ['jalali_date', 'jalali_request_date', 'jalali_follow_up_date', 'status_label', 'source_label'];

    protected $casts = [
        'request_date' => 'date',
        'follow_up_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function ratings()
    {
        return $this->hasMany(Rating::class , "after_sale_service_id");
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function installationService(): BelongsTo
    {
        return $this->belongsTo(InstallationService::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }


    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function getJalaliDateAttribute()
    {
        $jalaliDate = new Verta($this->created_at);
        return $jalaliDate->format('Y/m/d ساعت H:i');
    }

    public function getJalaliRequestDateAttribute()
    {
        if ($this->request_date != null) {
            $jalaliDate = new Verta($this->request_date);
            return $jalaliDate->format('Y/m/d');
        }
        return null;
    }

    public function getJalaliFollowUpDateAttribute()
    {
        if ($this->follow_up_date != null) {
            $jalaliDate = new Verta($this->follow_up_date);
            return $jalaliDate->format('Y/m/d');
        }
        return null;
    }

    public function getStatusLabelAttribute()
    {
        return self::STATUS_LABELS[$this->status] ?? null;
    }

    public function getSourceLabelAttribute()
    {
        return self::SOURCE_LABELS[$this->source] ?? null;
    }
}
