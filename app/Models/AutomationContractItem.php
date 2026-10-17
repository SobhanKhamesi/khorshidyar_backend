<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationContractItem extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';
    protected $fillable = [
        'automation_id',
        'title',
        'amount',
    ];
}
