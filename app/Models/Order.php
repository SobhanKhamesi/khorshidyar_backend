<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{

    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';


    protected $fillable = [
        'automation_id', 'product_id' , 'count', 'price','description'
    ];

    public $with = ['product'];

    public function product()
    {
        return $this->hasOne(Product::class, 'id', 'product_id');
    }
    public function automation()
    {
        return $this->belongsTo(Automation::class);
    }

}
