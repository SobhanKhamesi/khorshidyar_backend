<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationHomeSurvey extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'automation_home_surveys';

    protected $fillable = [
        'automation_id','goals','meter_single_selected',
        'meter_single_count','meter_single_amp','meter_three_selected',
        'meter_three_count','meter_three_amp',
        'meter_demand_selected','meter_demand_count','meter_demand_amp',
        'bill_id','install_location_type','install_location_distance',
        'needs_extra_infrastructure','extra_infrastructure_note','has_internal_panel',
        'connection_point','cable_length','panel_location_note',
        'panel_photo','consumer_fridge_selected','consumer_fridge_count',
        'consumer_lighting_selected','consumer_lighting_watt','consumer_tv_selected',
        'consumer_rolling_door_selected','consumer_rolling_door_count','consumer_water_pump_selected',
        'consumer_elevator_selected','consumer_elevator_type','consumer_cctv_selected',
        'consumer_cctv_count','consumer_gas_cooler_selected','consumer_water_cooler_selected',
        'consumer_water_cooler_type','consumer_water_cooler_count','outage_items',
        'night_duration','system_type','main_decision_maker',
        'has_limitation','description',
    ];

    protected $casts = [
        'goals' => 'array',
        'outage_items' => 'array',
        'meter_single_selected' => 'boolean',
        'meter_three_selected' => 'boolean',
        'meter_demand_selected' => 'boolean',
        'needs_extra_infrastructure' => 'boolean',
        'has_internal_panel' => 'boolean',
        'consumer_fridge_selected' => 'boolean',
        'consumer_lighting_selected' => 'boolean',
        'consumer_tv_selected' => 'boolean',
        'consumer_rolling_door_selected' => 'boolean',
        'consumer_water_pump_selected' => 'boolean',
        'consumer_elevator_selected' => 'boolean',
        'consumer_cctv_selected' => 'boolean',
        'consumer_gas_cooler_selected' => 'boolean',
        'consumer_water_cooler_selected' => 'boolean',
        'has_limitation' => 'boolean',
    ];

    protected $appends = ['panel_photo_url'];

    public function getPanelPhotoUrlAttribute()
    {
        return $this->panel_photo ? config('webroot.url') . '/' . $this->panel_photo : null;
    }

    public function automation()
    {
        return $this->belongsTo(Automation::class, 'automation_id');
    }

    public function gasCoolers()
    {
        return $this->hasMany(AutomationHomeGasCooler::class, 'home_survey_id');
    }
}
