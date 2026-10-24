<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_home_surveys', function (Blueprint $table) {
            $table->unsignedInteger('meter_single_amp')->nullable()->after('meter_single_count');
            $table->unsignedInteger('meter_three_amp')->nullable()->after('meter_three_count');

            $table->boolean('meter_demand_selected')->default(false)->after('meter_three_amp');
            $table->unsignedInteger('meter_demand_count')->nullable()->after('meter_demand_selected');
            $table->unsignedInteger('meter_demand_amp')->nullable()->after('meter_demand_count');

            $table->string('bill_id')->nullable()->after('meter_demand_amp');

            // plant_to_inverter | device_to_inverter
            $table->string('install_location_type', 50)->nullable()->after('bill_id');
            $table->decimal('install_location_distance', 10, 2)->nullable()->after('install_location_type'); // متر

            $table->boolean('needs_extra_infrastructure')->nullable()->after('install_location_distance');
            $table->text('extra_infrastructure_note')->nullable()->after('needs_extra_infrastructure');
        });

        Schema::table('automation_industrial_surveys', function (Blueprint $table) {
            $table->unsignedInteger('meter_amp')->nullable()->after('meter_type');
            $table->string('bill_id')->nullable()->after('meter_amp');
        });
    }

    public function down(): void
    {
        Schema::table('automation_home_surveys', function (Blueprint $table) {
            $table->dropColumn([
                'meter_single_amp', 'meter_three_amp',
                'meter_demand_selected', 'meter_demand_count', 'meter_demand_amp',
                'bill_id', 'install_location_type', 'install_location_distance',
                'needs_extra_infrastructure', 'extra_infrastructure_note',
            ]);
        });

        Schema::table('automation_industrial_surveys', function (Blueprint $table) {
            $table->dropColumn(['meter_amp', 'bill_id']);
        });
    }
};
