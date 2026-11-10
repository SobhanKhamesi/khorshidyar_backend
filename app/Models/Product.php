<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Morilog\Jalali\Jalalian;

class Product extends Model
{


    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';


    // ---- kind ----
    const KIND_GOODS = 1;   // کالا
    const KIND_SERVICE = 2; // خدمت

    // ---- service_nature (فقط برای خدمت) ----
    const NATURE_COST = 1;   // از جنس هزینه (مثل حمل و نقل): از سود کم می‌شود
    const NATURE_INCOME = 2; // از جنس دریافتی (مثل خدمات ارائه شده به مشتری): به درآمد اضافه می‌شود

    const KIND_LABELS = [self::KIND_GOODS => 'کالا', self::KIND_SERVICE => 'خدمت'];
    const NATURE_LABELS = [self::NATURE_COST => 'هزینه', self::NATURE_INCOME => 'دریافتی'];

    protected $fillable = [
        'name',
        'kind',
        'service_nature',
        'first_inventory',
        'purchase_price',
        'selling_price',
        'description',
        'user_id',
        'unit_id',
        'category_id',
        'code',
        'technical',
        'picture',
    ];

    public $appends = [
        'inventory', 'sold_count','jalali_created','SabtKonande', 'kind_label', 'service_nature_label', 'service_amount'
    ];

    public $with = ['unit'];

    protected $casts = [
        'kind' => 'integer',
        'service_nature' => 'integer',
    ];

    public function isService(): bool
    {
        return (int)$this->kind === self::KIND_SERVICE;
    }

    public function isCostService(): bool
    {
        return $this->isService() && (int)$this->service_nature === self::NATURE_COST;
    }

    /**
     * خدمت قیمت خرید و فروش جداگانه ندارد و فقط یک مبلغ دارد:
     * خدمت هزینه ← مبلغ هزینه (در purchase_price) / خدمت دریافتی ← مبلغ دریافتی (در selling_price)
     */
    public function getServiceAmountAttribute(): ?int
    {
        if (!$this->isService())
            return null;

        $amount = $this->isCostService() ? $this->purchase_price : $this->selling_price;
        return $amount === null ? null : (int)$amount;
    }

    public function getKindLabelAttribute(): ?string
    {
        return self::KIND_LABELS[$this->kind ?? self::KIND_GOODS] ?? null;
    }

    public function getServiceNatureLabelAttribute(): ?string
    {
        return $this->isService() ? (self::NATURE_LABELS[$this->service_nature] ?? null) : null;
    }

    // مقادیر کمکی scopeWithStats
    protected $hidden = ['reserved_count', 'sold_orders_count'];

    /**
     * محاسبه موجودی، تعداد فروش و ثبت کننده در همان کوئری اصلی به جای ۳ کوئری برای هر محصول.
     * شرط‌ها بدون binding هستند تا در کنار pluck/select/exists امن باشند.
     */
    public function scopeWithStats(Builder $query): Builder
    {
        return $query
            ->with('user')
            ->withSum(['orders as reserved_count' => fn($q) => $q->whereHas(
                'automation', fn($a) => $a->whereRaw('automation.status <> ' . Automation::FAILURE)
            )], 'count')
            ->withSum(['orders as sold_orders_count' => fn($q) => $q->whereHas(
                'automation', fn($a) => $a->whereRaw('automation.status = ' . Automation::SUCCESS)
            )], 'count');
    }

    // خدمت موجودی انبار ندارد
    public function getInventoryAttribute()
    {
        if ($this->isService())
            return null;

        $notAvailableInStock = array_key_exists('reserved_count', $this->attributes)
            ? ($this->attributes['reserved_count'] ?? 0)
            : Order::where('product_id', $this->id)
                ->whereHas('automation', fn($query) => $query->whereNot('status', Automation::FAILURE))
                ->sum('count');

        return $this->first_inventory - $notAvailableInStock;
    }


    public function getSoldCountAttribute()
    {
        if (array_key_exists('sold_orders_count', $this->attributes))
            return $this->attributes['sold_orders_count'] ?? 0;

        return Order::where('product_id', $this->id)
            ->whereHas('automation', fn($query) => $query->where('status', Automation::SUCCESS))
            ->sum('count');
    }
    public function getJalaliCreatedAttribute()
    {
        // created_at به وقت تهران (timezone برنامه) ذخیره می‌شود؛ نیازی به افزودن اختلاف ساعت نیست
        if (!$this->created_at)
            return null;
        return Jalalian::fromCarbon($this->created_at)->format('Y/m/d ساعت H:i');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function getSabtKonandeAttribute()
    {
        return $this->user?->name;
    }
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }


}
