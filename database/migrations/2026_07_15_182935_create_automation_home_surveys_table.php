<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('automation_home_surveys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUUid('automation_id')->unique()->comment("کدام اتوماسیون فروش");

            $table->json('goals')->nullable()->comment("هدف مشتری"); // ['برق پشتیبان', ...]

            $table->boolean('meter_single_selected')->default(false)->comment("کنتور تک فاز دارد؟");
            $table->unsignedInteger('meter_single_count')->nullable()->comment("تعداد کنتور تک فاز");
            $table->boolean('meter_three_selected')->default(false)->comment("کنتور سه فاز دارد؟");
            $table->unsignedInteger('meter_three_count')->nullable()->comment("تعداد کنتور سه فاز");

            $table->text('install_location_note')->nullable()->comment("تخمین محل نصب دستگاه");
            $table->boolean('has_internal_panel')->nullable()->comment("تابلو برق داخلی در واحد دارد؟");
            $table->string('connection_point')->nullable()->comment("محل اتصال سیستم به مصرف کننده"); // کنتور اصلی | تابلو برق واحد
            $table->unsignedInteger('cable_length')->nullable()->comment("مقدار کابل مورد نیاز");
            $table->text('panel_location_note')->nullable()->comment("محل نصب پنل ها");

            $table->string('panel_photo')->nullable()->comment("عکس محل نصب پنل");

            // مصرف کننده ها
            $table->boolean('consumer_fridge_selected')->default(false)->comment("یخچال و فریزر دارد؟");
            $table->unsignedInteger('consumer_fridge_count')->nullable()->comment("تعداد یخچال و فریزر");

            $table->boolean('consumer_lighting_selected')->default(false)->comment("روشنایی دارد؟");
            $table->unsignedInteger('consumer_lighting_watt')->nullable()->comment("ولتاژ روشنایی");

            $table->boolean('consumer_tv_selected')->default(false)->comment("تلویزیون دارد؟");

            $table->boolean('consumer_rolling_door_selected')->default(false)->comment("کرکره برقی و درب اتوماتیک دارد؟");
            $table->unsignedInteger('consumer_rolling_door_count')->nullable()->comment("تعداد کرکره برقی و درب اتوماتیک");

            $table->boolean('consumer_water_pump_selected')->default(false)->comment("پمپ آب دارد؟");

            $table->boolean('consumer_elevator_selected')->default(false)->comment("آسانسور دارد؟");
            $table->string('consumer_elevator_type')->nullable()->comment("نوع آسانسور"); // کششی | هیدرولیک

            $table->boolean('consumer_cctv_selected')->default(false)->comment("دوربین مدار بسته دارد؟");
            $table->unsignedInteger('consumer_cctv_count')->nullable()->comment("تعداد دوربین مدار بسته");

            $table->boolean('consumer_gas_cooler_selected')->default(false)->comment("کولر گازی دارد؟");

            $table->boolean('consumer_water_cooler_selected')->default(false)->comment("کولر آبی دارد؟");
            $table->string('consumer_water_cooler_type')->nullable()->comment("نوع کولر آبی");
            $table->unsignedInteger('consumer_water_cooler_count')->nullable()->comment("تعداد کولر آبی");


            $table->json('outage_items')->nullable()->comment("اقلام مصرفی در رمان قطعی برق");
            $table->unsignedInteger('night_duration')->nullable()->comment("مدت زمان روشن بودن در شب");
            $table->string('system_type')->nullable()->comment("نوع سیستم");
            $table->string('main_decision_maker')->nullable()->comment("تصمیم گیرنده اصلی");
            $table->boolean('has_limitation')->nullable()->comment("آیا برای اجرا محدودیت خاصی وجود دارد یا آماده شروع نصب هستید؟");
            $table->text('description')->nullable();

            $table->timestamps();
        });

        Schema::create('automation_home_gas_coolers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUUid('home_survey_id')->comment("کدام فرم بازدید خانگی");
            $table->string('size')->comment("ظرفیت");       // 12 | 18 | 24 | 30 | 36
            $table->boolean('inverter')->default(false)->comment("اینورتر یا غیر اینورتر");
            $table->unsignedInteger('count')->comment("تعداد");
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_home_gas_coolers');
        Schema::dropIfExists('automation_home_surveys');
    }
};
