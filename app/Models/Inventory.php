<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'inventory';

    protected $fillable = [
        'title',
        'description',
        'user_id'
    ];

    public function productGroups()
    {
        return $this->hasMany(ProductCategory::class);
    }


}
