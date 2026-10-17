<?php

namespace App\Models;

use App\Traits\Uuids;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Planning extends Model
{

    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';


    protected $table = 'planning';

    const ACTIVE = 0;
    const COMPLETED = 1;

    public $appends = [
        'jalali_date', 'duration',
    ];

    protected $fillable = [
        'title',
        'date',
        'time_start',
        'time_end',
        'status',
        'description',
        'user_id'
    ];


    public function getJalaliDateAttribute()
    {
        $jalaliDate = new Verta($this->date);
        return $jalaliDate->format('Y/m/d');
    }

    public function getDurationAttribute()
    {
        return (new Carbon($this->time_end))->diffInMinutes(new Carbon($this->time_start));
    }

}
