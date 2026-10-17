<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationIndustrialMainConsumer extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'automation_industrial_main_consumers';
    protected $fillable = ['industrial_survey_id', 'name', 'power', 'type', 'starter_type'];

    public function industrialSurvey()
    {
        return $this->belongsTo(AutomationIndustrialSurvey::class, 'industrial_survey_id');
    }
}
