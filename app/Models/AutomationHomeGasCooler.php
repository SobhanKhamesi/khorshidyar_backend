<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationHomeGasCooler extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'automation_home_gas_coolers';
    protected $fillable = [
        'home_survey_id',
        'size',
        'inverter',
        'count',
    ];
    protected $casts = ['inverter' => 'boolean'];

    public function homeSurvey()
    {
        return $this->belongsTo(AutomationHomeSurvey::class, 'home_survey_id');
    }
}
