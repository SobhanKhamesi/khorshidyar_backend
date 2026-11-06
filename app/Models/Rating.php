<?php

namespace App\Models;

use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Rating extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';


    protected $fillable = [
        'after_sale_service_id',
        'token',
        'rating',
        'feedback',
        'submitted_at',
        'review_description',
        'reviewed_by',
        'reviewed_at',
    ];

    // عنوان قالب پیامک نظرسنجی در تنظیمات پیامک؛ {link} با لینک صفحه نظرسنجی جایگزین می‌شود
    const SMS_TEMPLATE_TITLE = 'نظرسنجی';

    const DEFAULT_SMS_TEXT = "لطفاً با ثبت نظر خود درباره‌ی خدمات ارائه شده، ما را در بهبود خدمات یاری کنید.\n\n"
        . "لینک صفحه نظرسنجی:\n"
        . "{link}";

    // ---- سطح رضایت بر اساس امتیاز ----
    const LEVEL_POOR = 1;       // ضعیف: 0 تا 2
    const LEVEL_AVERAGE = 2;    // متوسط: بیشتر از 2 تا کمتر از 4
    const LEVEL_EXCELLENT = 3;  // عالی: 4 تا 5

    const LEVEL_LABELS = [
        self::LEVEL_POOR => 'ضعیف',
        self::LEVEL_AVERAGE => 'متوسط',
        self::LEVEL_EXCELLENT => 'عالی',
    ];

    // حداکثر امتیازی که «ضعیف» محسوب می‌شود و حداقل امتیاز «عالی»
    const POOR_MAX = 2;
    const EXCELLENT_MIN = 4;

    protected $casts = [
        'rating' => 'decimal:1',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public $appends = ['is_submitted', 'level', 'level_label', 'needs_review', 'jalali_reviewed_at'];

    public function afterSalesService()
    {
        return $this->belongsTo(AfterSalesService::class, 'after_sale_service_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getIsSubmittedAttribute(): bool
    {
        return !is_null($this->submitted_at);
    }

    public static function levelFor($rating): ?int
    {
        if ($rating === null)
            return null;

        $rating = (float)$rating;
        if ($rating <= self::POOR_MAX)
            return self::LEVEL_POOR;
        if ($rating < self::EXCELLENT_MIN)
            return self::LEVEL_AVERAGE;
        return self::LEVEL_EXCELLENT;
    }

    public function getLevelAttribute(): ?int
    {
        return $this->is_submitted ? self::levelFor($this->rating) : null;
    }

    public function getLevelLabelAttribute(): ?string
    {
        return self::LEVEL_LABELS[$this->level] ?? null;
    }

    // نظرسنجی ضعیف که هنوز دلیل نارضایتی آن بررسی نشده (آلارم)
    public function getNeedsReviewAttribute(): bool
    {
        return $this->level === self::LEVEL_POOR && is_null($this->reviewed_at);
    }

    public function getJalaliReviewedAtAttribute(): ?string
    {
        return $this->reviewed_at ? (new Verta($this->reviewed_at))->format('Y/m/d ساعت H:i') : null;
    }

    public function scopePoor($query)
    {
        return $query->whereNotNull('submitted_at')->where('rating', '<=', self::POOR_MAX);
    }

    public function scopeNeedsReview($query)
    {
        return $query->poor()->whereNull('reviewed_at');
    }

    /**
     * تولید توکن یکتای غیرقابل حدس برای لینک عمومی نظرسنجی.
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    public static function getOrCreateFor(AfterSalesService $service)
    {
        return DB::transaction(function () use ($service) {
            $survey = self::where('after_sale_service_id', $service->id)->first();

            if ($survey)
                return $survey;

            return self::create([
                'after_sale_service_id' => $service->id,
                'token' => self::generateToken(),
            ]);
        });
    }
}
