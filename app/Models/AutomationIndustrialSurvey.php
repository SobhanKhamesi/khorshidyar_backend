<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationIndustrialSurvey extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'automation_industrial_surveys';

    protected $fillable = [
        'automation_id','goals','meter_type','meter_amp','bill_id',
        'branch_capacity','has_dedicated_transformer',
        'outage_per_week','outage_hours_each','outage_time',
        'outage_effects','required_photos','must_stay_on_equipment',
        'damage_at_moment','damage_per_hour','night_backup_duration',
        'install_location','has_diesel_generator','diesel_capacity',
        'diesel_fuel_consumption','diesel_working_hours',
        'system_preference','payment_type','final_decision_maker',
        'description',
    ];

    protected $casts = [
        'goals' => 'array',
        'outage_effects' => 'array',
        'required_photos' => 'array',
        'has_dedicated_transformer' => 'boolean',
        'has_diesel_generator' => 'boolean',
    ];

    // در مدل Survey
    protected $appends = ['required_photos_urls'];

    public function getRequiredPhotosUrlsAttribute()
    {
        if (empty($this->required_photos))
            return [];

        return array_map(function ($path) {
            return config('webroot.url') . '/' . $path;
        }, $this->required_photos);
    }
    public function automation()
    {
        return $this->belongsTo(Automation::class, 'automation_id');
    }

    public function mainConsumers()
    {
        return $this->hasMany(AutomationIndustrialMainConsumer::class, 'industrial_survey_id');
    }
}
