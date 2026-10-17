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
        Schema::create('morakhasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('status')->nullable();
            $table->integer('Type')->nullable();
            $table->string('typeMorakhasi')->nullable();
            $table->dateTime('date')->nullable();
            $table->time('startTime')->nullable();
            $table->time('endTime')->nullable();
            $table->dateTime('startDate')->nullable();
            $table->dateTime('endDate')->nullable();
            $table->string('alternative')->nullable();
            $table->char('accept_admin')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('morakhasi');
    }
};
