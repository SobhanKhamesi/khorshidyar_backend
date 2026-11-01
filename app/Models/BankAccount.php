<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{

    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'bank_accounts';

    protected $fillable = [
        'bank_id',
        'number',
        'description',
        'user_id'
    ];

    public $with = ['bank'];
    public $appends = ['fullDetails'];


    public function getFullDetailsAttribute()
    {
        return $this->number . ' - ' . $this->bank->name;
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

}
