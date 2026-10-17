<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyReportItem extends Model
{

    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';


    protected $table = 'daily_report_items';
    public $timestamps = false;

    protected $fillable = [
        'report_id',
        'time',
        'body'
    ];

    public function report()
    {
        return $this->hasOne(DailyReport::class);
    }

}
