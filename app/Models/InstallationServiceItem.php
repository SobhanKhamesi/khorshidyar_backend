<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallationServiceItem extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    // ---- type ----
    const TYPE_REQUIRED = 1; // اقلام پیش‌نیاز (تعیین شده توسط مدیر هنگام ارجاع به نصاب)
    const TYPE_USED = 2;     // اقلام مصرف شده (ثبت شده توسط نصاب در پایان نصب)

    protected $fillable = [
        'installation_service_id',
        'product_id',
        'type',
        'quantity',
    ];

    // مقدار می‌تواند اعشاری باشد (مثلاً متر کابل)؛ واحد از محصول خوانده می‌شود
    protected $casts = [
        'quantity' => 'float',
        'type' => 'integer',
    ];

    public function installationService(): BelongsTo
    {
        return $this->belongsTo(InstallationService::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
