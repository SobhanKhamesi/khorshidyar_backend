<?php

namespace App\Models;

use App\Services\ProjectProfitService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * هزینه یا دریافتی جانبی یک پروژه (سرویس نصب)؛ مثل حمل و نقل، کارشناس فنی، هزینه‌های پیش‌بینی نشده
 * یا خدمات ارائه شده به مشتری. می‌تواند به یک خدمت تعریف شده در محصولات وصل باشد یا عنوان آزاد داشته باشد.
 */
class InstallationServiceFinanceItem extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    // ---- direction ----
    const COST = 1;   // هزینه: از سود کم می‌شود
    const INCOME = 2; // دریافتی: به درآمد پروژه اضافه می‌شود

    const DIRECTION_LABELS = [self::COST => 'هزینه', self::INCOME => 'دریافتی'];

    // ---- source ----
    const SOURCE_MANUAL = 1;  // ثبت دستی مدیر
    const SOURCE_INVOICE = 2; // منتقل شده از خدمات فاکتور فروش

    protected $fillable = [
        'installation_service_id',
        'product_id',
        'direction',
        'source',
        'title',
        'amount',
        'description',
        'user_id',
    ];

    protected $casts = [
        'direction' => 'integer',
        'source' => 'integer',
        'amount' => 'integer',
    ];

    protected $appends = ['direction_label'];

    protected static function booted(): void
    {
        $refresh = fn(self $item) => ProjectProfitService::refreshById($item->installation_service_id);
        static::saved($refresh);
        static::deleted($refresh);
    }

    public function installationService(): BelongsTo
    {
        return $this->belongsTo(InstallationService::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutEagerLoads();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->select('id', 'name');
    }

    public function getDirectionLabelAttribute(): ?string
    {
        return self::DIRECTION_LABELS[$this->direction] ?? null;
    }
}
