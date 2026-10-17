<?php

namespace App\Models;

use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationPayment extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'automation_id',
        'method',
        'amount',
        'date',
    ];

    public $appends = ['jalali_date',];

    public function getJalaliDateAttribute()
    {
        $jalaliDate = new Verta($this->date);
        return $jalaliDate->format('Y/m/d');
    }
}
