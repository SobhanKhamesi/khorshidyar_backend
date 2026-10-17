<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_services', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('customer_id');

            // خرید شده از ما (internal) یا از جای دیگر (external)
            $table->tinyInteger('purchase_source');

            // فقط وقتی purchase_source = internal پر می‌شود؛ برای external همیشه null
            $table->foreignUuid('automation_id')->nullable();

            $table->string('address')->nullable();
            $table->string('contact_number')->nullable();
            $table->text('description')->nullable();

            // تاریخ بازدید - به صورت میلادی ذخیره می‌شود (ورودی از فرانت شمسی است و در کنترلر با Verta پارس می‌شود)
            $table->date('survey_date')->nullable();

            $table->foreignUuid('technical_expert_id')->nullable();

            // تکمیل موقت / تکمیل نهایی
            $table->tinyInteger('status');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_services');
    }
};
