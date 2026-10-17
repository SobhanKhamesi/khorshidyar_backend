<?php

namespace App\Models;

use App\Traits\Uuids;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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


    public function getInventoryAttribute()
    {
        $notAvailableInStock = Order::where('product_id', $this->id)
            ->whereHas('automation', function ($query) {
                $query->whereNot('status', Automation::FAILURE);
            })
            ->sum('count');
        return $this->first_inventory - $notAvailableInStock;
    }


    public function getSoldCountAttribute()
    {
        return Order::where('product_id', $this->id)
            ->whereHas('automation', function ($query) {
                $query->where('status', Automation::SUCCESS);
            })
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
        return $this->hasOne(User::class, 'id', 'user_id');
    }
    public function getSabtKonandeAttribute()
    {
        return $this->user->name;
    }
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function unit()
    {
        return $this->hasOne(Unit::class, 'id', 'unit_id');
    }

    public function category()
    {
        return $this->hasOne(ProductCategory::class, 'id', 'category_id');
    }


}
