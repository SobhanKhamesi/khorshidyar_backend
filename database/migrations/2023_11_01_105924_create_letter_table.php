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
        Schema::create('letter', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('type')->nullable();
            $table->dateTime('date')->nullable();
            $table->string('number')->nullable();
            $table->integer('receiverDroon')->nullable();
            $table->string('receiverBroon')->nullable();
            $table->string('editor')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter');
    }
};
