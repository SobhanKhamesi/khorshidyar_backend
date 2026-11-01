<?php

namespace App\Models;

use App\Traits\Uuids;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{

    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';


    protected $table = 'daily_reports';

    const FINISHED = 1;
    const STARTED = 0;

    protected $fillable = [
        'user_id',
        'date',
        'start_time',
        'end_time',
        'status'
    ];

    public $with = ['user','items'];

    public $appends = ['jalali_date'];


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getJalaliDateAttribute()
    {
        $jalaliDate = new Verta($this->date);
        return $jalaliDate->format('Y/m/d');
    }


    public function items()
    {
        return $this->hasMany(DailyReportItem::class, 'report_id', 'id');
    }

}
