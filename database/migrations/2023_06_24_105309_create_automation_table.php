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
        Schema::create('automation', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('number');
            $table->tinyInteger('status');
            $table->date('date');

            $table->date('date_bar')->nullable();
            $table->string('type_car')->nullable();
            $table->string('type_tasviyehesab')->nullable();
            $table->string('car_number')->nullable();
            $table->string('name_barbary')->nullable();
            $table->string('tarkhiskar')->nullable();
            $table->string('number_havale')->nullable();
            $table->string('name_ranande')->nullable();
            $table->string('number_bijak')->nullable();
            $table->string('tel_ranande')->nullable();
            $table->string('codemeli_ranande')->nullable();

            $table->integer('final_price')->nullable();
            $table->text('Text_canceling')->nullable();
            $table->text('description')->nullable();
            $table->string('failure_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation');
    }
};
