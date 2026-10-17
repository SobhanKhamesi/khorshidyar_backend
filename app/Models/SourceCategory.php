<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SourceCategory extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'source_categories';

    protected $fillable = [
        'title',
        'description',
        'user_id'
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

}
