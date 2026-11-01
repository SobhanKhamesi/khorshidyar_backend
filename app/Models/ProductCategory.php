<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'product_categories';

    protected $fillable = [
        'title',
        'description',
        'inventory_id',
        'user_id'
    ];

    public $with = ['inventory', 'user'];
    public $appends = ['SabtKonande', 'SabtInventory'];

    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'inventory_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getSabtKonandeAttribute()
    {
        if (isset($this->user)) {
            return $this->user->name;
        } else {
            return 'نام کاربر نامشخص';
            // یا می‌توانید یک پیام خطا برگردانید
            // throw new Exception('مقدار user معتبر نیست');
        }
    }

    public function getSabtInventoryAttribute()
    {
        if (isset($this->inventory)) {
            return $this->inventory->title;
        } else {
            return 'نام کاربر نامشخص';
            // یا می‌توانید یک پیام خطا برگردانید
            // throw new Exception('مقدار user معتبر نیست');
        }
    }

    public function product()
    {
        return $this->hasMany(Product::class , 'category_id' , 'id');
    }

}
