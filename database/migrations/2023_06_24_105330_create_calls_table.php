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
        Schema::create('calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject');
            $table->string('type_call')->nullable();
            $table->string('Deal');
            $table->date('date');
            $table->time('time');
            $table->string('contacted')->nullable();
            $table->string('re_follow_subject')->nullable();
            $table->date('re_follow_date')->nullable();
            $table->time('re_follow_time')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
