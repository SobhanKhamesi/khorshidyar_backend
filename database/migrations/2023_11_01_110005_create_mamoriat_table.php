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
        Schema::create('mamoriat', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('status')->nullable();
            $table->integer('gender')->nullable();
            $table->string('fname')->nullable();
            $table->dateTime('date')->nullable();
            $table->string('vahed')->nullable();
            $table->string('time')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('moraje')->nullable();
            $table->string('day')->nullable();
            $table->char('accept_admin')->nullable();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mamoriat');
    }
};
