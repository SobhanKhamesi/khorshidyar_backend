<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فرم‌های بازدید (کارشناسی) خانگی و صنعتی اتوماسیون.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_home_surveys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('automation_id')->unique()->constrained('automation')->cascadeOnDelete()->comment('کدام اتوماسیون فروش');
            $table->json('goals')->nullable()->comment('هدف مشتری');
            // کنتورها
            $table->boolean('meter_single_selected')->default(false)->comment('کنتور تک فاز دارد؟');
            $table->unsignedInteger('meter_single_count')->nullable()->comment('تعداد کنتور تک فاز');
            $table->unsignedInteger('meter_single_amp')->nullable();
            $table->boolean('meter_three_selected')->default(false)->comment('کنتور سه فاز دارد؟');
            $table->unsignedInteger('meter_three_count')->nullable()->comment('تعداد کنتور سه فاز');
            $table->unsignedInteger('meter_three_amp')->nullable();
            $table->boolean('meter_demand_selected')->default(false)->comment('کنتور دیماندی دارد؟');
            $table->unsignedInteger('meter_demand_count')->nullable();
            $table->unsignedInteger('meter_demand_amp')->nullable();
            $table->string('bill_id')->nullable()->comment('شناسه قبض');
            // محل نصب
            $table->string('install_location_type', 50)->nullable()->comment('plant_to_inverter | device_to_inverter');
            $table->decimal('install_location_distance', 10, 2)->nullable()->comment('متر');
            $table->boolean('needs_extra_infrastructure')->nullable();
            $table->text('extra_infrastructure_note')->nullable();
            $table->text('install_location_note')->nullable()->comment('تخمین محل نصب دستگاه');
            $table->boolean('has_internal_panel')->nullable()->comment('تابلو برق داخلی در واحد دارد؟');
            $table->string('connection_point')->nullable()->comment('محل اتصال سیستم به مصرف کننده');
            $table->unsignedInteger('cable_length')->nullable()->comment('مقدار کابل مورد نیاز');
            $table->text('panel_location_note')->nullable()->comment('محل نصب پنل ها');
            $table->string('panel_photo')->nullable()->comment('عکس محل نصب پنل');
            // مصرف کننده ها
            $table->boolean('consumer_fridge_selected')->default(false);
            $table->unsignedInteger('consumer_fridge_count')->nullable();
            $table->boolean('consumer_lighting_selected')->default(false);
            $table->unsignedInteger('consumer_lighting_watt')->nullable();
            $table->boolean('consumer_tv_selected')->default(false);
            $table->boolean('consumer_rolling_door_selected')->default(false);
            $table->unsignedInteger('consumer_rolling_door_count')->nullable();
            $table->boolean('consumer_water_pump_selected')->default(false);
            $table->boolean('consumer_elevator_selected')->default(false);
            $table->string('consumer_elevator_type')->nullable()->comment('کششی | هیدرولیک');
            $table->boolean('consumer_cctv_selected')->default(false);
            $table->unsignedInteger('consumer_cctv_count')->nullable();
            $table->boolean('consumer_gas_cooler_selected')->default(false);
            $table->boolean('consumer_water_cooler_selected')->default(false);
            $table->string('consumer_water_cooler_type')->nullable();
            $table->unsignedInteger('consumer_water_cooler_count')->nullable();
            $table->json('outage_items')->nullable()->comment('اقلام مصرفی در زمان قطعی برق');
            $table->unsignedInteger('night_duration')->nullable()->comment('مدت زمان روشن بودن در شب');
            $table->string('system_type')->nullable()->comment('نوع سیستم');
            $table->string('main_decision_maker')->nullable()->comment('تصمیم گیرنده اصلی');
            $table->boolean('has_limitation')->nullable()->comment('محدودیت خاصی برای اجرا وجود دارد؟');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('automation_home_gas_coolers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('home_survey_id')->constrained('automation_home_surveys')->cascadeOnDelete();
            $table->string('size')->comment('ظرفیت: 12 | 18 | 24 | 30 | 36');
            $table->boolean('inverter')->default(false);
            $table->unsignedInteger('count');
            $table->timestamps();
        });

        Schema::create('automation_industrial_surveys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('automation_id')->unique()->constrained('automation')->cascadeOnDelete()->comment('کدام اتوماسیون فروش');
            $table->json('goals')->nullable()->comment('هدف از پروژه');
            $table->string('meter_type')->nullable()->comment('نوع کنتور');
            $table->unsignedInteger('meter_amp')->nullable();
            $table->string('bill_id')->nullable();
            $table->unsignedInteger('branch_capacity')->nullable();
            $table->boolean('has_dedicated_transformer')->nullable();
            $table->unsignedTinyInteger('outage_per_week')->nullable();
            $table->unsignedInteger('outage_hours_each')->nullable();
            $table->string('outage_time')->nullable();
            $table->json('outage_effects')->nullable();
            $table->json('required_photos')->nullable();
            $table->text('must_stay_on_equipment')->nullable();
            $table->unsignedBigInteger('damage_at_moment')->nullable();
            $table->unsignedBigInteger('damage_per_hour')->nullable();
            $table->string('night_backup_duration')->nullable();
            $table->string('install_location')->nullable();
            $table->boolean('has_diesel_generator')->nullable();
            $table->unsignedInteger('diesel_capacity')->nullable();
            $table->unsignedInteger('diesel_fuel_consumption')->nullable();
            $table->unsignedInteger('diesel_working_hours')->nullable();
            $table->string('system_preference')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('final_decision_maker')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('automation_industrial_main_consumers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('industrial_survey_id');
            $table->string('name');
            $table->unsignedInteger('power')->nullable();
            $table->string('type')->nullable();
            $table->string('starter_type')->nullable();
            $table->timestamps();

            // نام پیش‌فرض constraint از 64 کاراکتر MySQL بیشتر می‌شود
            $table->foreign('industrial_survey_id', 'aimc_industrial_survey_id_foreign')
                ->references('id')->on('automation_industrial_surveys')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_industrial_main_consumers');
        Schema::dropIfExists('automation_industrial_surveys');
        Schema::dropIfExists('automation_home_gas_coolers');
        Schema::dropIfExists('automation_home_surveys');
    }
};
