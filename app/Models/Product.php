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


    protected $fillable = [
        'name',
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
        'inventory', 'sold_count','jalali_created','SabtKonande'
    ];

    public $with = ['unit'];

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

    public function getInventoryAttribute()
    {
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
        $createdDateTime = $this->attributes['created_at'];
        $carbonDateTime = Carbon::parse($createdDateTime, 'UTC')->addHours(3)->addMinutes(30);
        $jalaliDateTime = Jalalian::fromCarbon($carbonDateTime);
        return $jalaliDateTime->format('Y/m/d ساعت H:i');
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
