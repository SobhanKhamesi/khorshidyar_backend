<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // شماره همراه کاربران؛ برای ارسال پیامک ارجاع به نصاب و اعلام شماره نصاب به مشتری
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile')->nullable()->after('username');
        });

        // مرحله‌ی گردش کار سرویس نصب (ارجاع به نصاب ← تکمیل نصاب ← بررسی کارشناس ← تکمیل نهایی)
        Schema::table('installation_services', function (Blueprint $table) {
            $table->unsignedTinyInteger('stage')->default(0)->after('status');
            $table->timestamp('completed_at')->nullable()->after('stage');
        });

        // سرویس‌های نهایی شده‌ی قبلی تکمیل نهایی محسوب می‌شوند و بقیه پیش‌نویس
        DB::table('installation_services')->where('status', 2)->update(['stage' => 6]);

        // 1: اقلام پیش‌نیاز (تعیین شده توسط مدیر هنگام ارجاع)، 2: اقلام مصرف شده (ثبت شده توسط نصاب)
        Schema::table('installation_service_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('type')->default(1)->after('product_id');
        });

        // تاریخچه‌ی گردش کار: هر تغییر مرحله همراه با توضیح (مثلاً عیوب اعلام شده توسط کارشناس)
        Schema::create('installation_service_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('installation_service_id');
            $table->foreignUuid('user_id')->nullable();
            $table->unsignedTinyInteger('stage');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_service_histories');

        Schema::table('installation_service_items', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('installation_services', function (Blueprint $table) {
            $table->dropColumn(['stage', 'completed_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mobile');
        });
    }
};
