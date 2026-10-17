<?php

namespace App\Models;

use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationCheque extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';


    protected $fillable = [
        'automation_id',
        'number',
        'bank_name',
        'amount',
        'due_date',
    ];

    public $appends = ['jalali_due_date',];

    public function getJalaliDueDateAttribute()
    {
        $jalaliDate = new Verta($this->due_date);
        return $jalaliDate->format('Y/m/d');
    }
}
