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
        Schema::create('mosaede', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('status')->nullable();
            $table->string('price')->nullable();
            $table->string('title')->nullable();
            $table->char('accept_admin')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mosaede');
    }
};
