<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installer_appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id'); // نصاب
            $table->foreignUuid('automation_id')->nullable();
            $table->foreignUuid('installation_service_id')->nullable();
            $table->foreignUuid('after_sale_service_id')->nullable();

            // type: 1=نصب پنل, 2=نصب سیم‌کشی, 3=نصب و راه‌اندازی
            $table->unsignedTinyInteger('type');

            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');

            // status: 1=رزرو شده, 2=انجام شده, 3=لغو شده
            $table->unsignedTinyInteger('status')->default(1);

            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installer_appointments');
    }
};
