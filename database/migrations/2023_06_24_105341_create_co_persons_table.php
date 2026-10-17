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
        Schema::create('co_persons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('gender');
            $table->string('name');
            $table->string('contact_number')->nullable();
            $table->string('position')->nullable();
            $table->string('disc')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('co_persons');
    }
};
