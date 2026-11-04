<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جداول پایه / دسته‌بندی‌ها که سایر جداول به آن‌ها ارجاع می‌دهند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
        });

        Schema::create('units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('service_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('referrers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // منبع جذب مشتری
        Schema::create('source_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('customer_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();
        });

        // دلایل شکست فروش
        Schema::create('failure_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();
        });

        // قالب‌های پیامک (مدل SourceSms)
        Schema::create('sms_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title')->index();
            $table->text('description')->nullable();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_categories');
        Schema::dropIfExists('failure_categories');
        Schema::dropIfExists('customer_categories');
        Schema::dropIfExists('source_categories');
        Schema::dropIfExists('referrers');
        Schema::dropIfExists('service_types');
        Schema::dropIfExists('units');
        Schema::dropIfExists('banks');
    }
};
