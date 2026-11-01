<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نوع سوم فرم بازدید: «ثبت شده از مشتری» (survey_type = 3).
 * مشتری این فرم را از طریق لینک پیامک شده تکمیل می‌کند و بعداً در سامانه قابل ویرایش است.
 * پاسخ‌ها از این پس در همین جدول (به‌عنوان فرم بازدید درخواست) ذخیره می‌شوند و
 * جدول customer_intake_forms فقط وضعیت لینک ارسال شده را نگه می‌دارد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_customer_surveys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('automation_id')->unique();
            $table->string('full_name', 100)->nullable()->comment('نام و نام خانوادگی');
            $table->string('national_code', 10)->nullable()->comment('کد ملی');
            $table->string('bill_id', 20)->nullable()->comment('شناسه قبض برق');
            $table->text('address')->nullable()->comment('آدرس محل نصب');
            $table->decimal('available_area', 12, 2)->nullable()->comment('فضای در دسترس (متر مربع)');
            $table->string('install_place', 50)->nullable()->comment('مکان نصب');
            $table->string('building_age', 50)->nullable()->comment('سن بنا');
            $table->string('ownership', 50)->nullable()->comment('نوع مالکیت');
            $table->string('goal')->nullable()->comment('هدف از نصب نیروگاه');
            $table->string('property_type', 50)->nullable()->comment('مشخصات ملک');
            $table->json('surface_material')->nullable()->comment('جنس سطح');
            $table->string('mobile', 11)->nullable()->comment('شماره موبایل');
            $table->timestamps();
        });

        Schema::table('customer_intake_forms', function (Blueprint $table) {
            $table->dropColumn('answers');
        });
    }

    public function down(): void
    {
        Schema::table('customer_intake_forms', function (Blueprint $table) {
            $table->json('answers')->nullable()->after('sent_by');
        });

        Schema::dropIfExists('automation_customer_surveys');
    }
};
