<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('automation_industrial_surveys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUUid('automation_id')->unique()->comment("کدام اتوماسیون فروش");

            $table->json('goals')->nullable()->comment("هدف از پروژه");

            $table->string('meter_type')->nullable()->comment("نوع کنتور");
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
            $table->foreignUUid('industrial_survey_id');
            $table->string('name');
            $table->unsignedInteger('power')->nullable();
            $table->string('type')->nullable();
            $table->string('starter_type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_industrial_main_consumers');
        Schema::dropIfExists('automation_industrial_surveys');
    }
};
