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
        Schema::table('letter', function (Blueprint $table) {
            $table->drop();
        });
        Schema::table('mamoriat', function (Blueprint $table) {
            $table->drop();
        });
        Schema::table('morakhasi', function (Blueprint $table) {
            $table->drop();
        });
        Schema::table('mosaede', function (Blueprint $table) {
            $table->drop();
        });
        Schema::table('over_time', function (Blueprint $table) {
            $table->drop();
        });
        Schema::table('kala', function (Blueprint $table) {
            $table->drop();
        });
        Schema::table('advertising', function (Blueprint $table) {
            $table->drop();
        });
        Schema::table('orders_over_time', function (Blueprint $table) {
            $table->drop();
        });
        Schema::table('orderkala', function (Blueprint $table) {
            $table->drop();
        });
        Schema::table('deals', function (Blueprint $table) {
            $table->drop();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
